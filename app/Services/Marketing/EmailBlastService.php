<?php

namespace App\Services\Marketing;

use App\Jobs\SendEmailBlast;
use App\Models\EmailBlast;
use App\Models\EmailBlastSetting;
use App\Models\User;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message as ImapMessage;

/**
 * Tools › Email Blast: kirim (SMTP) & baca inbox (IMAP) akun email hosting tersendiri, terpisah dari MAIL_* di .env.
 * Tiap penerima dikirimi email sendiri-sendiri (alamat penerima lain tidak terlihat), lewat antrean, dengan batas harian.
 */
class EmailBlastService
{
    public const MAX_RECIPIENTS = 200;

    /** Penerima per putaran job: tiap putaran selesai jauh di bawah timeout job. */
    public const BATCH = 5;

    public const INBOX_PER_PAGE = 20;

    /** Ambil alamat email dari teks tempelan (koma, titik koma, spasi, baris baru, atau "Nama <email>"), tanpa dobel. */
    public static function parseRecipients(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return array_values(array_unique(array_filter(
            array_map('strtolower', $matches[0]),
            fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        )));
    }

    /** Jumlah email yang sudah diantrekan hari ini (dasar batas harian). */
    public function usedToday(): int
    {
        return (int) EmailBlast::where('created_at', '>=', today())->get(['recipients'])->sum(fn ($b) => count($b->recipients ?? []));
    }

    /** $user null = dikirim sistem (Agent Prospek, draf yang sudah disetujui). */
    public function queue(string $subject, string $body, array $recipients, ?User $user): EmailBlast
    {
        $setting = EmailBlastSetting::current();

        if (! $setting->isConfigured()) {
            throw new RuntimeException('Akun email pengirim belum diatur. Isi dulu tab Pengaturan.');
        }

        if ($recipients === []) {
            throw new RuntimeException('Tidak ada alamat email penerima yang valid.');
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw new RuntimeException('Maksimal '.self::MAX_RECIPIENTS.' penerima per kiriman.');
        }

        $blast = DB::transaction(function () use ($setting, $subject, $body, $recipients, $user) {
            // Kunci baris pengaturan: dua kiriman bersamaan tidak bisa sama-sama lolos batas harian.
            EmailBlastSetting::whereKey($setting->getKey())->lockForUpdate()->first();
            $remaining = $setting->daily_limit - $this->usedToday();

            if (count($recipients) > $remaining) {
                throw new RuntimeException('Melebihi batas harian '.$setting->daily_limit.' email. Sisa hari ini: '.max(0, $remaining).'.');
            }

            return EmailBlast::create(['subject' => $subject, 'body' => $body, 'recipients' => $recipients, 'created_by' => $user?->getKey()]);
        });

        SendEmailBlast::dispatch($blast->getKey());

        return $blast;
    }

    /** Dijalankan job: kirim maksimal BATCH penerima yang belum diproses, lalu lanjut di job berikutnya. */
    public function sendBatch(string $blastId): bool
    {
        $blast = EmailBlast::find($blastId);

        if (! $blast || $blast->status === 'done') {
            return false;
        }

        $setting = EmailBlastSetting::current();
        $results = $blast->results ?? [];
        $pending = array_slice(array_values(array_diff($blast->recipients, array_keys($results))), 0, self::BATCH);
        $blast->forceFill(['status' => 'sending'])->save();
        $mailer = $pending ? $this->mailer($setting) : null;

        foreach ($pending as $email) {
            try {
                $mailer->html($this->html($blast->body), function (Message $message) use ($setting, $blast, $email) {
                    $message->from($setting->from_address, $setting->from_name ?: null)->to($email)->subject($blast->subject);
                    $message->getSymfonyMessage()->text($blast->body);
                });
                $results[$email] = 'sent';
            } catch (Throwable $e) {
                $results[$email] = mb_substr($e->getMessage(), 0, 200);
            }

            // Disimpan per penerima: kalau worker mati di tengah, penerima yang sudah terkirim tidak dikirimi ulang.
            $blast->forceFill(['results' => $results])->save();
        }

        $done = count($results) >= count($blast->recipients);
        $sent = count(array_filter($results, fn ($r) => $r === 'sent'));
        $blast->forceFill([
            'sent_count' => $sent,
            'failed_count' => count($results) - $sent,
            'status' => $done ? 'done' : 'sending',
            'finished_at' => $done ? now() : null,
        ])->save();

        return ! $done;
    }

    /** Daftar email masuk terbaru (tanpa isi, supaya cepat & tidak menandai sudah dibaca). */
    public function inbox(int $page): array
    {
        $client = $this->imap();

        try {
            $folder = $client->getFolderByPath('INBOX');
            $total = $folder->messages()->all()->count();
            $messages = $folder->messages()->all()->setFetchBody(false)->setFetchOrderDesc()->limit(self::INBOX_PER_PAGE, $page)->get();

            return [
                'total' => $total,
                'messages' => $messages->map(fn (ImapMessage $m) => [
                    'uid' => $m->getUid(),
                    'from' => $this->sender($m),
                    'subject' => (string) $m->getSubject() ?: '(tanpa subjek)',
                    'date' => $m->getDate()?->toDate(),
                    'seen' => $m->getFlags()->has('seen'),
                // Pustaka memilih halaman terbaru, tapi isinya tetap urut lama -> baru: dibalik di sini.
                ])->sortByDesc('uid')->values()->all(),
            ];
        } finally {
            $client->disconnect();
        }
    }

    /** Satu email masuk lengkap (ditandai sudah dibaca, seperti di aplikasi email biasa). */
    public function message(int $uid): array
    {
        $client = $this->imap();

        try {
            $m = $client->getFolderByPath('INBOX')->query()->getMessageByUid($uid);

            return [
                'uid' => $uid,
                'from' => $this->sender($m),
                'to' => collect($m->getTo()?->toArray() ?? [])->map(fn ($a) => $a->mail)->implode(', '),
                'subject' => (string) $m->getSubject() ?: '(tanpa subjek)',
                'date' => $m->getDate()?->toDate(),
                'html' => $m->hasHTMLBody() ? $m->getHTMLBody() : null,
                'text' => $m->getTextBody(),
                'attachments' => $m->getAttachments()->map(fn ($a) => $a->getName())->filter()->values()->all(),
            ];
        } finally {
            $client->disconnect();
        }
    }

    /** Cek login SMTP & IMAP dengan pengaturan yang (akan) disimpan. Mengembalikan pesan error, null kalau berhasil. */
    public function test(EmailBlastSetting $setting): ?string
    {
        try {
            $transport = $this->mailer($setting)->getSymfonyTransport();
            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            return 'SMTP gagal: '.mb_substr($e->getMessage(), 0, 200);
        }

        try {
            $this->imap($setting)->disconnect();
        } catch (Throwable $e) {
            return 'IMAP gagal: '.mb_substr($e->getMessage(), 0, 200);
        }

        return null;
    }

    private function mailer(EmailBlastSetting $setting): Mailer
    {
        return Mail::build([
            'transport' => 'smtp',
            'scheme' => $setting->encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $setting->host,
            'port' => $setting->smtp_port,
            'username' => $setting->username,
            'password' => $setting->password,
            'timeout' => 15,
        ]);
    }

    private function imap(?EmailBlastSetting $setting = null): Client
    {
        $setting ??= EmailBlastSetting::current();

        if (! $setting->isConfigured()) {
            throw new RuntimeException('Akun email belum diatur. Isi dulu tab Pengaturan.');
        }

        return (new ClientManager(['options' => ['fetch_order' => 'desc']]))->make([
            'host' => $setting->host,
            'port' => $setting->imap_port,
            'encryption' => $setting->encryption,
            'validate_cert' => true,
            'username' => $setting->username,
            'password' => $setting->password,
            'protocol' => 'imap',
            'timeout' => 15,
        ])->connect();
    }

    private function html(string $body): string
    {
        return '<div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #222">'.nl2br(e($body)).'</div>';
    }

    private function sender(ImapMessage $m): string
    {
        $from = $m->getFrom()?->first();

        return $from ? trim(($from->personal ? $from->personal.' ' : '').'<'.$from->mail.'>') : '-';
    }
}
