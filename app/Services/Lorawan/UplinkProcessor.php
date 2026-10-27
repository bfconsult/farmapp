<?php

namespace App\Services\Lorawan;

use App\Models\Device;
use App\Models\LorawanEvent;
use App\Models\SensorReading;
use App\Services\Lorawan\Decoders\InvalidPayload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Decodes a stored uplink into SensorReadings, following the gateway's
 * ChirpStack-style JSON (see "Captured samples" in
 * docs/lorawan-ingest-spec.md): hex `data`, `fPort`, `fCnt`, lowercase
 * `devEUI`, `rxInfo` per gateway that heard it, `timestamp` in epoch
 * seconds.
 */
class UplinkProcessor
{
    /**
     * Uplinks from a DevEUI the property hasn't registered are left
     * unprocessed, so they show up as unrecognised and can be decoded once
     * the device is added. So are uplinks not yet tied to a property (sent
     * with the old shared key by a gateway no property has registered).
     */
    public function process(LorawanEvent $event): void
    {
        if ($event->event !== 'uplink' || $event->processed_at) {
            return;
        }

        $device = $event->dev_eui && $event->property_id
            ? Device::where('property_id', $event->property_id)->where('dev_eui', $event->dev_eui)->first()
            : null;
        if (!$device) {
            return;
        }

        $body = $event->body;

        try {
            $values = $this->decode($device, $body);
        } catch (InvalidPayload $e) {
            Log::warning('LoRaWAN uplink could not be decoded', ['event_id' => $event->id, 'device_id' => $device->id, 'error' => $e->getMessage()]);
            $event->update(['processed_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 255)]);

            return;
        }

        $recordedAt = $this->recordedAt($body, $event);
        $signal = $this->bestSignal($body['rxInfo'] ?? []);
        $fCnt = isset($body['fCnt']) ? (int) $body['fCnt'] : null;

        DB::transaction(function () use ($event, $device, $values, $recordedAt, $signal, $fCnt) {
            // insertOrIgnore - a retried uplink hits the unique index and
            // is skipped rather than stored twice.
            SensorReading::insertOrIgnore(collect($values)->map(fn ($value, $metric) => [
                'device_id' => $device->id,
                'lorawan_event_id' => $event->id,
                'recorded_at' => $recordedAt,
                'metric' => $metric,
                'value' => $value,
                'rssi' => $signal['rssi'],
                'snr' => $signal['snr'],
                'f_cnt' => $fCnt,
            ])->values()->all());

            // A backlog decoded late mustn't overwrite fresher device status.
            if (!$device->last_seen_at || $recordedAt->gte($device->last_seen_at)) {
                $device->forceFill([
                    'last_seen_at' => $recordedAt,
                    'battery' => $values['battery'] ?? $device->battery,
                    'last_rssi' => $signal['rssi'],
                    'last_snr' => $signal['snr'],
                ])->save();
            }

            $event->update(['processed_at' => now(), 'error' => null]);
        });
    }

    /**
     * Every unprocessed uplink for this device, oldest first - after it's
     * registered, or after its decoder is added or fixed.
     */
    public function processBacklog(Device $device): int
    {
        $count = 0;
        LorawanEvent::where('property_id', $device->property_id)
            ->where('dev_eui', $device->dev_eui)
            ->where('event', 'uplink')
            ->whereNull('processed_at')
            ->orderBy('id')
            ->each(function (LorawanEvent $event) use (&$count) {
                $this->process($event);
                $count++;
            });

        return $count;
    }

    private function decode(Device $device, array $body): array
    {
        if (!DeviceModels::exists($device->model)) {
            throw new InvalidPayload("No decoder for model {$device->model}");
        }

        $hex = $body['data'] ?? '';
        if (($body['data_encode'] ?? 'hexstring') !== 'hexstring' || !is_string($hex) || !ctype_xdigit($hex) || strlen($hex) % 2) {
            throw new InvalidPayload('Payload is not a hex string');
        }

        return DeviceModels::decoder($device->model)->decode(hex2bin($hex), (int) ($body['fPort'] ?? 0));
    }

    /**
     * The gateway's own receive time, unless it's missing or implausible
     * (a gateway with no clock sync reports 1970) - then when we got it.
     */
    private function recordedAt(array $body, LorawanEvent $event): Carbon
    {
        $timestamp = $body['timestamp'] ?? null;
        if (is_numeric($timestamp)) {
            $at = Carbon::createFromTimestamp((int) $timestamp);
            if ($at->year >= 2020 && $at->lte(now()->addMinutes(5))) {
                return $at;
            }
        }

        return $event->received_at ?? now();
    }

    /**
     * The strongest of the gateways that heard it (there's only one today).
     */
    private function bestSignal(array $rxInfo): array
    {
        $best = collect($rxInfo)->filter(fn ($rx) => isset($rx['rssi']))->sortByDesc('rssi')->first();

        return [
            'rssi' => isset($best['rssi']) ? (int) $best['rssi'] : null,
            'snr' => isset($best['loRaSNR']) ? (float) $best['loRaSNR'] : null,
        ];
    }
}
