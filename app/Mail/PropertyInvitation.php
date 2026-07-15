<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PropertyInvitation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Invitation $invitation)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->invitation->property->name} on Fieldwerkz",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.property-invitation',
            with: ['bodyMessage' => $this->invitation->message ?: $this->defaultMessage()],
        );
    }

    /**
     * The sentence used when the inviter didn't customize the message.
     */
    private function defaultMessage(): string
    {
        return sprintf(
            '%s has invited you to join %s on Fieldwerkz as a %s.',
            $this->invitation->invitedBy->name,
            $this->invitation->property->name,
            ucfirst($this->invitation->role)
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
