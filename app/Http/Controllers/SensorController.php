<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Gateway;
use App\Models\LorawanEvent;
use App\Models\SensorReading;
use App\Services\Lorawan\DeviceModels;
use App\Services\Lorawan\UplinkProcessor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * LoRaWAN sensors (docs/lorawan-ingest-spec.md, Phase 2). A sensor is
 * registered by its DevEUI; every field its decoder returns is stored, and
 * each device picks which of them to show.
 */
class SensorController extends Controller
{
    public function index()
    {
        $propertyId = session('current_property_id');

        $devices = Device::where('property_id', $propertyId)
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device) => [
                ...$this->summary($device),
                'latest' => $this->latestValues($device),
            ]);

        return Inertia::render('Manage/Sensors/Index', [
            'devices' => $devices,
            'gateways' => Gateway::where('property_id', $propertyId)->orderBy('name')->get(['id', 'name', 'gateway_eui', 'last_seen_at']),
            'unrecognised' => $this->unrecognised($propertyId),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Manage/Sensors/Edit', [
            'device' => null,
            'models' => DeviceModels::options(),
            'prefill' => [
                'dev_eui' => $request->query('dev_eui', ''),
                'name' => $request->query('name', ''),
            ],
        ]);
    }

    /**
     * Anything the gateway already captured from this DevEUI is decoded
     * straight away, so the sensor arrives with its history.
     */
    public function store(Request $request, UplinkProcessor $processor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model' => ['required', Rule::in(array_keys(DeviceModels::MODELS))],
            'dev_eui' => ['required', 'regex:/^[0-9a-fA-F]{16}$/'],
        ], [
            'dev_eui.regex' => 'The DevEUI is 16 hex characters (0-9, A-F), printed on the sensor\'s label.',
        ]);
        $validated['dev_eui'] = strtolower($validated['dev_eui']);

        $request->merge(['dev_eui' => $validated['dev_eui']])->validate([
            'dev_eui' => Rule::unique('devices')->where('property_id', session('current_property_id')),
        ], [
            'dev_eui.unique' => 'A sensor with this DevEUI has already been added.',
        ]);

        $device = Device::create([
            ...$validated,
            'property_id' => session('current_property_id'),
        ]);

        $processor->processBacklog($device);

        return redirect()->route('manage.sensors.show', $device)->with('success', 'Sensor added.');
    }

    /**
     * The inspector: every field the sensor has reported with its latest
     * value, and recent uplinks raw alongside what they decoded to.
     */
    public function show(Device $device)
    {
        $this->authorizeDevice($device);

        $events = $device->events()
            ->where('event', 'uplink')
            ->latest('id')
            ->take(20)
            ->get();
        $readingsByEvent = SensorReading::whereIn('lorawan_event_id', $events->pluck('id'))
            ->get()
            ->groupBy('lorawan_event_id');

        return Inertia::render('Manage/Sensors/Show', [
            'device' => $this->summary($device),
            'metrics' => $this->metricRows($device),
            'events' => $events->map(fn (LorawanEvent $event) => [
                'id' => $event->id,
                // When the sensor sent it (what its readings are dated by),
                // falling back to arrival for one not decoded.
                'recorded_at' => ($readingsByEvent[$event->id] ?? null)?->first()->recorded_at ?? $event->received_at,
                'processed_at' => $event->processed_at,
                'error' => $event->error,
                'f_cnt' => $event->body['fCnt'] ?? null,
                'f_port' => $event->body['fPort'] ?? null,
                'data' => $event->body['data'] ?? null,
                'body' => $event->body,
                'values' => ($readingsByEvent[$event->id] ?? collect())->pluck('value', 'metric'),
            ]),
        ]);
    }

    public function edit(Device $device)
    {
        $this->authorizeDevice($device);

        return Inertia::render('Manage/Sensors/Edit', [
            'device' => $this->summary($device),
            'models' => DeviceModels::options(),
            'prefill' => null,
        ]);
    }

    /**
     * Only the name - the DevEUI and model decide how its stored uplinks
     * were decoded, so changing them means removing and re-adding it.
     */
    public function update(Request $request, Device $device)
    {
        $this->authorizeDevice($device);

        $device->update($request->validate(['name' => 'required|string|max:255']));

        return redirect()->route('manage.sensors.show', $device)->with('success', 'Sensor updated.');
    }

    public function updateShownMetrics(Request $request, Device $device)
    {
        $this->authorizeDevice($device);

        $validated = $request->validate([
            'shown_metrics' => 'present|array',
            'shown_metrics.*' => ['string', Rule::in(array_keys(DeviceModels::metrics($device->model)))],
        ]);

        $device->update(['config' => [...($device->config ?? []), 'shown_metrics' => array_values(array_unique($validated['shown_metrics']))]]);

        return back();
    }

    /**
     * Its readings go with it. The raw uplinks stay and become undecoded
     * again, so re-adding the DevEUI (e.g. to fix the model) rebuilds them.
     */
    public function destroy(Device $device)
    {
        $this->authorizeDevice($device);

        $device->events()->update(['processed_at' => null, 'error' => null]);
        $device->delete();

        return redirect()->route('manage.sensors.index')->with('success', 'Sensor removed.');
    }

    private function authorizeDevice(Device $device): void
    {
        abort_unless($device->property_id === (int) session('current_property_id'), 404);
    }

    private function summary(Device $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'model' => $device->model,
            'model_label' => DeviceModels::MODELS[$device->model]['label'] ?? $device->model,
            'dev_eui' => $device->dev_eui,
            'last_seen_at' => $device->last_seen_at,
            'battery' => $device->battery !== null ? (float) $device->battery : null,
            'last_rssi' => $device->last_rssi,
            'last_snr' => $device->last_snr !== null ? (float) $device->last_snr : null,
            'shown_metrics' => $device->shownMetrics(),
        ];
    }

    /**
     * The shown fields' newest values, in the order they were chosen.
     */
    private function latestValues(Device $device): array
    {
        $latest = $device->latestReadings();
        $definitions = DeviceModels::metrics($device->model);

        return collect($device->shownMetrics())
            ->filter(fn ($metric) => isset($latest[$metric]))
            ->map(fn ($metric) => [
                'metric' => $metric,
                'value' => $latest[$metric]->value,
                ...array_intersect_key($definitions[$metric] ?? [], array_flip(['label', 'unit', 'states'])),
            ])
            ->values()
            ->all();
    }

    /**
     * Every field the model defines plus any the decoder has produced that
     * it doesn't (so nothing reported is ever invisible), with its latest
     * value and whether it's shown.
     */
    private function metricRows(Device $device): array
    {
        $latest = $device->latestReadings();
        $definitions = DeviceModels::metrics($device->model);
        $shown = $device->shownMetrics();

        return collect(array_keys($definitions))
            ->merge(array_keys($latest))
            ->unique()
            ->map(fn ($metric) => [
                'metric' => $metric,
                'label' => $definitions[$metric]['label'] ?? $metric,
                'unit' => $definitions[$metric]['unit'] ?? null,
                'states' => $definitions[$metric]['states'] ?? null,
                'shown' => in_array($metric, $shown, true),
                'value' => isset($latest[$metric]) ? $latest[$metric]->value : null,
                'recorded_at' => isset($latest[$metric]) ? $latest[$metric]->recorded_at : null,
            ])
            // Fields this sensor has actually reported first - a door
            // sensor in door mode never sends the leak ones.
            ->sortBy(fn ($row) => $row['recorded_at'] === null)
            ->values()
            ->all();
    }

    /**
     * DevEUIs this property's gateways have heard that it hasn't added,
     * with their newest uplink so you can see what they send.
     */
    private function unrecognised(?int $propertyId): array
    {
        $registered = Device::where('property_id', $propertyId)->pluck('dev_eui');

        return LorawanEvent::where('property_id', $propertyId)
            ->where('event', 'uplink')
            ->whereNotNull('dev_eui')
            ->whereNotIn('dev_eui', $registered)
            ->selectRaw('dev_eui, count(*) as uplinks, max(id) as latest_id')
            ->groupBy('dev_eui')
            ->get()
            ->map(function ($row) {
                $latest = LorawanEvent::find($row->latest_id);

                return [
                    'dev_eui' => $row->dev_eui,
                    'uplinks' => (int) $row->uplinks,
                    'device_name' => $latest->body['deviceName'] ?? null,
                    'received_at' => $latest->received_at,
                    'body' => $latest->body,
                ];
            })
            ->sortByDesc('received_at')
            ->values()
            ->all();
    }
}
