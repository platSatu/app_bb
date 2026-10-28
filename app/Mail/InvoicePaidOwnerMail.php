<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Kabar ke owner: invoice lunas (paid), atau uang masuk untuk invoice yang sudah lunas/dibatalkan (refund).
 * $amount = nominal yang dibayar customer pada transaksi ini.
 */
class InvoicePaidOwnerMail extends Mailable
{
    use Queueable;

    private ?Invoice $invoice = null;

    public function __construct(public string $invoiceId, public string $kind, public int $amount)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'refund'
            ? "Perlu refund: pembayaran ganda {$this->invoice()->number}"
            : "Invoice lunas {$this->invoice()->number} · {$this->invoice()->customer_name}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.invoice-paid-owner', with: ['invoice' => $this->invoice(), 'kind' => $this->kind, 'amount' => $this->amount]);
    }

    private function invoice(): Invoice
    {
        return $this->invoice ??= Invoice::with(['company', 'branch'])->findOrFail($this->invoiceId);
    }
}
