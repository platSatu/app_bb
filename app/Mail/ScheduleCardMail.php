<?php

namespace App\Mail;

use App\Models\ScheduleStudent;
use App\Services\Media\QrCodeGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;

/** Kartu absen QR murid ke email terdaftar: link kartu + gambar QR terlampir (bisa disimpan / dicetak). */
class ScheduleCardMail extends Mailable
{
    use Queueable;

    private ?ScheduleStudent $student = null;

    public function __construct(public string $studentId)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Kartu absen '.$this->student()->name.' · '.($this->student()->branch?->name ?? config('app.name')));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.schedule-card', with: ['student' => $this->student()]);
    }

    public function attachments(): array
    {
        $png = base64_decode(Str::after(app(QrCodeGenerator::class)->dataUri($this->student()->qrCode(), 10), ','));

        return [Attachment::fromData(fn () => $png, 'kartu-absen-'.Str::slug($this->student()->name).'.png')->withMime('image/png')];
    }

    private function student(): ScheduleStudent
    {
        return $this->student ??= ScheduleStudent::with('branch:id,name')->findOrFail($this->studentId);
    }
}
