<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierJobRequest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->quote->farmJob->property->name} has a job for you",
            replyTo: array_filter([$this->quote->farmJob->property->email]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.supplier-job-request',
        );
    }
}
