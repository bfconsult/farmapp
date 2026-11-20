<?php

namespace App\Mail;

use App\Models\Device;
use App\Services\Lorawan\SensorKinds;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TankLow extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{percent: float, litres: ?int}  $level
     */
    public function __construct(public Device $device, public array $level)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->device->name} is down to ".round($this->level['percent']).'%',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tank-low',
            with: [
                'device' => $this->device,
                'percent' => round($this->level['percent']),
                'litres' => $this->level['litres'],
                'warnBelow' => (float) SensorKinds::setting($this->device, 'warn_below_percent'),
            ],
        );
    }
}
