<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayBalanceAlert extends Mailable
{
    use Queueable, SerializesModels;

    public int $failedCount;
    public string $date;

    public function __construct(int $failedCount, string $date)
    {
        $this->failedCount = $failedCount;
        $this->date        = $date;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Birthday SMS Failed — Insufficient mNotify Balance',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.birthday-balance-alert',
        );
    }
}
