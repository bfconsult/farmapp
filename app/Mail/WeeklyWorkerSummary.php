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

class WeeklyWorkerSummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection $summary Each entry: ['property' => Property, 'total_hours' => float, 'jobs' => Collection<['label' => string, 'sessions' => Collection<WorkSession>, 'total_hours' => float]>]
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
        return new Envelope(
            subject: 'Your Weekly Hours Summary',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.weekly-worker-summary',
            with: [
                'summary' => $this->summary,
                'totalHours' => $this->summary->sum('total_hours'),
            ],
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
