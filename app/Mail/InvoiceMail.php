<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Email invoice ke customer: issued (tagihan baru), reminder (pengingat), paid (bukti lunas). */
class InvoiceMail extends Mailable
{
    use Queueable;

    private ?Invoice $invoice = null;

    // Hanya id yang masuk antrean; data dibaca ulang saat email dikirim.
    public function __construct(public string $invoiceId, public string $kind)
    {
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

    private function invoice(): Invoice
    {
        return $this->invoice ??= Invoice::with(['company', 'category'])->findOrFail($this->invoiceId);
    }
}
