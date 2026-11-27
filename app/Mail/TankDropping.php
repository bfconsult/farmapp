<?php

namespace App\Mail;

use App\Models\Device;
use App\Services\Lorawan\TankActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TankDropping extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{percent: float, within_minutes: int}  $warning
     * @param  array{drop: float, from_percent: float, from_at: \Carbon\Carbon, percent: float, litres: ?int, from_litres: ?int}  $drop
     */
    public function __construct(public Device $device, public array $warning, public array $drop)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->device->displayName()} has dropped ".round($this->drop['drop']).'% in '.TankActivity::describeMinutes((int) $this->warning['within_minutes']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tank-dropping',
            with: [
                'device' => $this->device,
                'drop' => $this->drop,
                'warningPercent' => $this->warning['percent'] + 0,
                'within' => TankActivity::describeMinutes((int) $this->warning['within_minutes']),
            ],
        );
    }
}
