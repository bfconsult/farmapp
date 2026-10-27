<?php

namespace App\Http\Middleware;

use App\Models\Gateway;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * A LoRaWAN gateway authenticates with its own key in the
 * X-Fieldwerkz-Ingest-Key header (it can't hold a user session). The key
 * identifies the gateway, and so the property its events belong to.
 *
 * The old shared LORAWAN_INGEST_KEY still works while it's configured, so
 * the farm's gateway keeps reporting until it's switched to its own key.
 * Those events are tied to a property only if the gateway EUI in the body
 * is registered. Remove the env var to turn this off.
 */
class VerifyLorawanKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $given = (string) $request->header('X-Fieldwerkz-Ingest-Key');
        $gateway = Gateway::findByKey($given);

        if ($gateway) {
            $bodyEuis = $this->gatewayEuis($request);
            // The body names the gateway(s) that heard it. If it names
            // others but not this one, the key was pasted into the wrong
            // gateway - don't file its events under this property.
            if ($bodyEuis && !in_array($gateway->gateway_eui, $bodyEuis, true)) {
                Log::warning('LoRaWAN key used by a different gateway', ['gateway_id' => $gateway->id, 'body_euis' => $bodyEuis]);
                abort(403, 'Gateway EUI does not match this key.');
            }
        } else {
            $sharedKey = (string) config('services.lorawan.key');
            abort_if($sharedKey === '' || !hash_equals($sharedKey, $given), 401);

            $gateway = Gateway::whereIn('gateway_eui', $this->gatewayEuis($request))->first();
        }

        $request->attributes->set('lorawan_gateway', $gateway);

        return $next($request);
    }

    /**
     * Lowercase gateway EUIs from the body's rxInfo (uplinks); none for
     * events that don't carry it.
     */
    public static function gatewayEuis(Request $request): array
    {
        return collect($request->json('rxInfo', []))
            ->pluck('gatewayID')
            ->filter(fn ($eui) => is_string($eui) && preg_match('/^[0-9a-fA-F]{16}$/', $eui))
            ->map(fn ($eui) => strtolower($eui))
            ->unique()
            ->values()
            ->all();
    }
}
