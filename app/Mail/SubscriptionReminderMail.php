<?php

namespace App\Mail;

use App\Models\BranchSubscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Pengingat paket akan habis ke owner; daftar layanan dibaca ulang saat dikirim (yang sudah diperpanjang tidak ikut). */
class SubscriptionReminderMail extends Mailable
{
    use Queueable;

    public function __construct(public string $userId, public int $days, public array $subscriptionIds)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: match (true) {
            $this->days === 0 => 'Paket Anda berakhir hari ini',
            $this->days === 1 => 'Paket Anda berakhir besok',
            default => "Paket Anda berakhir {$this->days} hari lagi",
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.subscription-reminder', with: [
            'user' => User::findOrFail($this->userId),
            'days' => $this->days,
            'items' => BranchSubscription::with('branch:id,name')->whereKey($this->subscriptionIds)
                ->where('ends_at', '<=', now()->addDays($this->days)->endOfDay())->orderBy('ends_at')->get(),
        ]);
    }
}
