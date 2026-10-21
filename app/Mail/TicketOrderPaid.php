<?php

namespace App\Mail;

use App\Models\TicketEmailLog;
use App\Models\TicketOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Throwable;

class TicketOrderPaid extends Mailable
{
    use Queueable;

    private ?TicketOrder $order = null;

    // Hanya id yang masuk antrean; data dibaca ulang saat email dikirim.
    public function __construct(public string $orderId, public ?string $logId = null)
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

    /** Dipanggil worker antrean; status "Terkirim" hanya setelah server email menerima pesannya. */
    public function send($mailer)
    {
        try {
            $sent = parent::send($mailer);
        } catch (Throwable $e) {
            self::markFailed($this->logId, $e);

            throw $e;
        }

        if ($this->logId) {
            TicketEmailLog::whereKey($this->logId)->update(['status' => TicketEmailLog::STATUS_SENT, 'error' => null, 'sent_at' => now()]);
        }

        return $sent;
    }

    /** Antrean menyerah setelah semua percobaan gagal. */
    public function failed(Throwable $e): void
    {
        self::markFailed($this->logId, $e);
    }

    public static function markFailed(?string $logId, Throwable $e): void
    {
        if ($logId) {
            TicketEmailLog::whereKey($logId)->where('status', '!=', TicketEmailLog::STATUS_SENT)
                ->update(['status' => TicketEmailLog::STATUS_FAILED, 'error' => Str::limit($e->getMessage(), 490)]);
        }
    }

    private function order(): TicketOrder
    {
        return $this->order ??= TicketOrder::with(['page.branch.company', 'tickets'])->findOrFail($this->orderId);
    }
}
