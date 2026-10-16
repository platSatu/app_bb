<?php

namespace App\Mail;

use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TicketOrderPaid extends Mailable
{
    use Queueable;

    private ?TicketOrder $order = null;

    // Hanya id yang masuk antrean; data dibaca ulang saat email dikirim.
    public function __construct(public string $orderId)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'E-ticket '.$this->order()->page->name.' · '.$this->order()->reference);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ticket-order-paid', with: ['order' => $this->order()]);
    }

    private function order(): TicketOrder
    {
        return $this->order ??= TicketOrder::with(['page.branch.company', 'tickets'])->findOrFail($this->orderId);
    }
}
