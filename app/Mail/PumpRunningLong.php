<?php

namespace App\Mail;

use App\Models\Device;
use App\Services\Lorawan\SensorKinds;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PumpRunningLong extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Device $device, public Carbon $since, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->device->name} has been running for {$this->minutes} minutes",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pump-running-long',
            with: [
                'device' => $this->device,
                'minutes' => $this->minutes,
                'limit' => (int) SensorKinds::setting($this->device, 'warn_after_minutes'),
            ],
        );
    }
}
