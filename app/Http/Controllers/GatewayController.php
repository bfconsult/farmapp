<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Gateway;
use App\Models\LorawanEvent;
use App\Services\Lorawan\UplinkProcessor;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * A property's own LoRaWAN gateways (Manage -> Sensors). Each gets a key
 * to put in its HTTP integration; the key is how the ingest endpoint knows
 * which property an event belongs to (see VerifyLorawanKey).
 */
class GatewayController extends Controller
{
    public function create()
    {
        return Inertia::render('Manage/Sensors/GatewayCreate');
    }

    public function store(Request $request, UplinkProcessor $processor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gateway_eui' => ['required', 'regex:/^[0-9a-fA-F]{16}$/'],
        ], [
            'gateway_eui.regex' => 'The Gateway EUI is 16 hex characters (0-9, A-F), shown in the gateway\'s settings or on its label.',
        ]);
        $validated['gateway_eui'] = strtolower($validated['gateway_eui']);

        $request->merge(['gateway_eui' => $validated['gateway_eui']])->validate([
            'gateway_eui' => 'unique:gateways,gateway_eui',
        ], [
            'gateway_eui.unique' => 'This gateway has already been added to a property.',
        ]);

        $gateway = new Gateway([...$validated, 'property_id' => session('current_property_id')]);
        $key = $gateway->issueKey();

        // Events this gateway sent with the old shared key before it was
        // added here weren't tied to any property - they're this one's.
        $claimed = LorawanEvent::whereNull('property_id')
            ->where('gateway_eui', $gateway->gateway_eui)
            ->update(['property_id' => $gateway->property_id, 'gateway_id' => $gateway->id]);
        if ($claimed) {
            Device::where('property_id', $gateway->property_id)->get()->each(fn (Device $device) => $processor->processBacklog($device));
        }

        return redirect()->route('manage.gateways.show', $gateway)->with('gatewayKey', $key);
    }

    /**
     * Setup instructions. The key is only included straight after it's
     * issued - after that only its hash exists.
     */
    public function show(Gateway $gateway)
    {
        $this->authorizeGateway($gateway);

        return Inertia::render('Manage/Sensors/Gateway', [
            'gateway' => $gateway->only(['id', 'name', 'gateway_eui', 'last_seen_at', 'created_at']),
            'issuedKey' => session('gatewayKey'),
            'eventUrls' => collect(['uplink', 'join', 'ack', 'status'])
                ->mapWithKeys(fn ($event) => [$event => route('lorawan.ingest', $event)]),
            'header' => 'X-Fieldwerkz-Ingest-Key',
        ]);
    }

    /**
     * The old key stops working immediately.
     */
    public function regenerateKey(Gateway $gateway)
    {
        $this->authorizeGateway($gateway);

        return redirect()->route('manage.gateways.show', $gateway)->with('gatewayKey', $gateway->issueKey());
    }

    /**
     * Its key stops working. Events it already sent, and the readings
     * decoded from them, stay.
     */
    public function destroy(Gateway $gateway)
    {
        $this->authorizeGateway($gateway);

        $gateway->delete();

        return redirect()->route('manage.sensors.index')->with('success', 'Gateway removed.');
    }

    private function authorizeGateway(Gateway $gateway): void
    {
        abort_unless($gateway->property_id === (int) session('current_property_id'), 404);
    }
}
