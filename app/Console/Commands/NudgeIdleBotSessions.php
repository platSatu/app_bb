<?php

namespace App\Console\Commands;

use App\Models\WaBotSession;
use App\Services\WhatsApp\Bot\BotRouter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Menyapa kontak yang berhenti di tengah chatbot flow, lalu menutup percakapan baik-baik
 * kalau tetap tidak dijawab. Tanpa ini sesi cuma mati diam-diam saat timeout flow habis,
 * tanpa pamit.
 *
 * Tahapnya disimpan di cache dengan kunci yang memuat updated_at sesi. Begitu kontak
 * membalas, FlowEngine menyimpan sesi -> updated_at berubah -> kuncinya ikut berganti dan
 * hitungan mulai dari nol lagi. Jadi tidak perlu kolom baru di tabel.
 */
class NudgeIdleBotSessions extends Command
{
    protected $signature = 'whatsapp:nudge-sessions';

    protected $description = 'Sapa kontak yang diam di tengah chatbot flow, tutup percakapan bila tetap tidak dijawab';

    /** Jeda terkecil yang boleh dipilih di form flow; dipakai juga sebagai saringan kasar di query. */
    private const MIN_MINUTES = 1;

    /**
     * Kalimatnya sengaja tanpa nama usaha: bizbos itu SaaS, jadi flow ini milik banyak
     * pelanggan yang berbeda -- menyebut merek mana pun akan salah di tempat lain.
     * Kalau nanti perlu bisa diatur sendiri tiap pelanggan, tambahkan kolom teksnya di
     * WaChatbotFlow dan baca dari situ, jangan ditulis tetap di sini.
     */
    private const NUDGE_TEXT = 'Halo Kak, apakah masih terhubung? 😊';

    private const CLOSING_TEXT = 'Terima kasih sudah menghubungi kami, Kak 🙏'
        ."\n\n".'Percakapan ini saya tutup dulu ya. Kalau nanti masih ada yang ingin ditanyakan, tinggal kirim pesan lagi — kita mulai dari awal dengan senang hati 😊';

    public function handle(BotRouter $bot): int
    {
        $disapa = 0;
        $ditutup = 0;

        // Hanya flow yang pemiliknya menyalakan saklarnya. Saringan menit di query sengaja
        // yang paling longgar, karena tiap flow boleh punya jedanya sendiri; jeda aslinya
        // dicek per baris di bawah.
        WaBotSession::with(['chat', 'flow'])
            ->whereHas('flow', fn ($q) => $q->whereNotNull('nudge_minutes'))
            ->where('updated_at', '<=', now()->subMinutes(self::MIN_MINUTES))
            ->where('expires_at', '>', now())
            ->limit(200)->get()
            ->each(function (WaBotSession $session) use ($bot, &$disapa, &$ditutup) {
                if (! $chat = $session->chat) {
                    return;
                }

                $jeda = (int) ($session->flow?->nudge_minutes ?? 0);

                if ($jeda < self::MIN_MINUTES || $session->updated_at->gt(now()->subMinutes($jeda))) {
                    return; // saklarnya mati, atau diamnya belum cukup lama untuk flow ini
                }

                // Isinya waktu saat disapa, bukan sekadar penanda: penutupan harus menunggu
                // satu jeda penuh lagi sejak sapaan, bukan satu putaran perintah ini.
                // Kuncinya memuat updated_at, jadi begitu kontak membalas kuncinya berganti
                // dan hitungan mulai dari nol tanpa perlu dibersihkan.
                $kunci = 'wa-nudge:'.$session->id.':'.$session->updated_at->timestamp;
                $disapaPada = Cache::get($kunci);

                if ($disapaPada === null) {
                    // nudge() menolak sendiri kalau chat sedang dipegang admin, batas balasan
                    // tercapai, atau langganan WhatsApp cabangnya tidak aktif. Kalau ditolak,
                    // waktunya tidak dicatat -> sesi dibiarkan habis sendiri seperti biasa.
                    if ($bot->nudge($chat, self::NUDGE_TEXT)) {
                        Cache::put($kunci, now()->timestamp, now()->addDay());
                        $disapa++;
                    }

                    return;
                }

                if (now()->timestamp - (int) $disapaPada < $jeda * 60) {
                    return; // sudah disapa, tapi belum satu jeda penuh sejak sapaan itu
                }

                // Sudah disapa dan tetap didiamkan: sesi ditutup apa pun hasil kirimnya,
                // supaya kontak mulai dari menu awal pada pesan berikutnya.
                $session->delete();

                if ($bot->nudge($chat, self::CLOSING_TEXT)) {
                    $ditutup++;
                }
            });

        $this->info("Disapa: {$disapa}, ditutup: {$ditutup}");

        return self::SUCCESS;
    }
}
