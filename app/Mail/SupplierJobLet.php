<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierJobLet extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote)
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Update on \"{$this->quote->farmJob->name}\"",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.supplier-job-let',
        );
    }
}
