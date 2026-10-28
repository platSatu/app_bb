<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\InvoiceEmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Throwable;

/** Email invoice ke customer: issued (tagihan baru), reminder (pengingat), paid (bukti lunas). */
class InvoiceMail extends Mailable
{
    use Queueable;

    private ?Invoice $invoice = null;

    // Bukan promoted property: email lama yang sudah di antrean (tanpa logId) tetap bisa dikirim.
    public ?string $logId = null;

    // Hanya id yang masuk antrean; data dibaca ulang saat email dikirim (setelah transaksi selesai).
    public function __construct(public string $invoiceId, public string $kind, ?string $logId = null)
    {
        $this->logId = $logId;
        $this->afterCommit();
    }

    /** Catat hasil kirim ke riwayat email invoice; error tetap dilempar supaya antrean bisa mencoba ulang. */
    public function send($mailer)
    {
        try {
            $sent = parent::send($mailer);
        } catch (Throwable $e) {
            $this->log(['status' => InvoiceEmailLog::STATUS_FAILED, 'error' => Str::limit($e->getMessage(), 480)]);

            throw $e;
        }

        $this->log(['status' => InvoiceEmailLog::STATUS_SENT, 'error' => null, 'sent_at' => now()]);

        return $sent;
    }

    public function envelope(): Envelope
    {
        $company = $this->invoice()->company?->name ?? config('app.name');

        return new Envelope(subject: match ($this->kind) {
            'paid' => "Pembayaran diterima · {$this->invoice()->number}",
            'reminder' => "Pengingat tagihan {$this->invoice()->number} dari {$company}",
            default => "Tagihan {$this->invoice()->number} dari {$company}",
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice', with: ['invoice' => $this->invoice(), 'kind' => $this->kind]);
    }

    private function log(array $data): void
    {
        if ($this->logId) {
            InvoiceEmailLog::whereKey($this->logId)->update($data);
        }
    }

    private function invoice(): Invoice
    {
        return $this->invoice ??= Invoice::with(['company', 'category'])->findOrFail($this->invoiceId);
    }
}
