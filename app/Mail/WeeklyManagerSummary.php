<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class WeeklyManagerSummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection $summary Each entry: ['property' => Property, 'jobs' => Collection<FarmJob>, 'measurements' => Collection<MetricMeasurement>]
     */
    public function __construct(public Collection $summary)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = $this->summary->count();

        return new Envelope(
            subject: "Weekly Summary: {$count} propert" . ($count === 1 ? 'y needs' : 'ies need') . ' attention',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.weekly-manager-summary',
            with: ['summary' => $this->summary],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
