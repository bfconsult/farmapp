<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyLorawanKey;
use App\Models\LorawanEvent;
use App\Services\Lorawan\UplinkProcessor;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

class LorawanIngestController extends Controller
{
    /**
     * Store the gateway's JSON exactly as received, against the property
     * whose gateway sent it (see VerifyLorawanKey), then decode it after
     * the response has gone back (docs/lorawan-ingest-spec.md). The raw
     * event is kept either way, so a decoding failure loses nothing.
     */
    public function store(Request $request, string $event)
    {
        $gateway = $request->attributes->get('lorawan_gateway');
        $body = $request->json()->all();
        $devEui = $body['devEUI'] ?? null;

        $lorawanEvent = LorawanEvent::create([
            'property_id' => $gateway?->property_id,
            'gateway_id' => $gateway?->id,
            'event' => $event,
            'dev_eui' => is_string($devEui) && preg_match('/^[0-9a-fA-F]{16}$/', $devEui) ? strtolower($devEui) : null,
            'gateway_eui' => $gateway?->gateway_eui ?? (VerifyLorawanKey::gatewayEuis($request)[0] ?? null),
            'body' => $body,
            // Set here rather than left to the column's CURRENT_TIMESTAMP
            // default, which uses MySQL's timezone, not the app's UTC.
            'received_at' => now(),
        ]);

        $gateway?->forceFill(['last_seen_at' => now()])->save();

        if ($event === 'uplink' && $gateway) {
            defer(fn () => app(UplinkProcessor::class)->process($lorawanEvent));
        }

        return response()->noContent();
    }
}
