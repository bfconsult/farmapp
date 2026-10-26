# FieldWerkz: LoRaWAN sensor ingest (spec for Claude Code)

## Goal
Receive readings from LoRaWAN sensors at the farm (tank level, gate open/close) into FieldWerkz, store them against a Property, raise alerts, and show them in the UI. Proof of concept first: one door/contact sensor, then a tank level sensor.

## Stack
Laravel 12 / Inertia.js / React 19 / Tailwind CSS 4. PWA plus companion mobile app. Existing models include Users, Properties, Roles, FarmJobs, WorkSessions, Photos, Invitations. Leaflet map already built.

## Architecture (decided)

```
sensor -> RAK7268V2 gateway (farm, Wi-Fi uplink via Starlink)
       -> gateway's built-in network server
       -> HTTPS POST (JSON) -> https://fieldwerkz.com/api/lorawan/{event}
       -> DB -> alerts + UI
```

Everything from the gateway is outbound-only (Starlink uses CGNAT, no inbound needed).

### Gateway configuration (already done by the user)
- Hardware: RAK WisGate Edge Lite 2 (RAK7268V2), AU915, antenna on RP-SMA, joined to the farm Wi-Fi (2.4 GHz).
- Work mode: Built-in network server.
- One application created with **Separate application keys** (each device has its own AppKey, entered when the device is added).
- **Payload format: None** (the built-in server only offers None or Cayenne LPP and cannot run custom decoders, so decoding happens in Laravel).
- Data format to the endpoint: **hex string**.
- "Report LoRa radio information": **on** (gives RSSI/SNR per uplink).
- "Only forward data object": **off**.
- HTTP/HTTPS integration enabled, one custom header: `X-Fieldwerkz-Ingest-Key: <shared secret>`.
- Event URLs:
  - Uplink: `https://fieldwerkz.com/api/lorawan/uplink`
  - Join: `https://fieldwerkz.com/api/lorawan/join`
  - ACK: `https://fieldwerkz.com/api/lorawan/ack`
  - Device status: `https://fieldwerkz.com/api/lorawan/status`
- **Use the bare domain, not `www`.** `https://www.fieldwerkz.com/...` answers with a 301 redirect to `https://fieldwerkz.com/...`, and a gateway either won't follow a redirect on a POST or re-sends it as a GET without the body, so every reading would be lost.
- Gateway config can be backed up (Settings > Backup and Restore, encrypted), but backups restore only onto the same gateway. So the master record of devices and keys must live in FieldWerkz.

### Not yet verified
- The exact JSON structure the gateway sends. Do NOT guess field names. Phase 1 captures real payloads first.
- The Dragino LDS02 payload layout. Take it from Dragino's published user manual and decoder, not from memory.

## Phase 1: capture-only endpoint (build now)

1. ~~`php artisan install:api`~~ Not needed: API routes and Sanctum were already set up for the mobile app. The LoRaWAN route sits outside the `auth:sanctum` group.
2. Migration `lorawan_events`: id, event (string), body (json), received_at (timestamp, default now).
3. Model `LorawanEvent` (no timestamps, guarded = [], casts body => array, received_at => datetime).
4. Middleware `VerifyLorawanKey`: compare `X-Fieldwerkz-Ingest-Key` header to `config('services.lorawan.key')` with `hash_equals`; abort 401 on mismatch or empty config. Add `'lorawan' => ['key' => env('LORAWAN_INGEST_KEY')]` to `config/services.php`. Secret goes in `.env` only, never committed, never stored in the DB.
5. Route in `routes/api.php`:
   `POST /lorawan/{event}`, `whereIn('event', ['uplink','join','ack','status'])`, middleware `VerifyLorawanKey` and `throttle:120,1`, controller `LorawanIngestController@store`.
   The api group's own `throttle:api` (60/min per IP) is removed from this route with `withoutMiddleware`, otherwise it would cap the gateway below 120. Every request comes from the farm's single Starlink IP, so this limit covers the whole gateway.
6. Controller `store`: create a `LorawanEvent` with `event` and `body = $request->json()->all()`; return 204.
7. Tests (Pest/PHPUnit): 401 without or with a wrong key; 204 and a stored row with the right key; 404 for an unknown event name.
8. Manual check:
   ```
   curl -X POST https://fieldwerkz.com/api/lorawan/uplink \
     -H "X-Fieldwerkz-Ingest-Key: $KEY" -H "Content-Type: application/json" \
     -d '{"test": true}'
   ```
   Expect 204 and a new row.

Phase 1 is complete: real uplinks were captured on 2026-10-26 (see "Captured samples").

## Phase 2: parse and store (after a real payload is captured)

### Tables
- `devices`: id, property_id (FK), name, type (`gate`, `tank`, ...), dev_eui (unique, 16 hex chars), app_key (encrypted cast; never log, never expose in the UI or API), config (json: tank height, capacity, offsets, thresholds, expected interval), last_seen_at, battery, last_rssi, last_snr.
- `sensor_readings`: id, device_id (FK), recorded_at, metric (string), value (decimal or json), rssi, snr, f_cnt, raw (json). Unique index on (device_id, f_cnt) for idempotency (the network server may retry). Index on (device_id, recorded_at).

### Processing
- Look up the device by DevEUI from the payload; ignore or log unknown devices.
- Decode the hex payload with a per-device-type decoder class (`DraginoLds02Decoder`, tank decoder later), built from the manufacturer's published layout.
- Do the work in a queued job so the endpoint returns quickly.
- Keep the raw body in `lorawan_events` for a retention window (for example 30 days), then prune.

### Device-specific
- **Gate / door (Dragino LDS02, AU915):** sends on each open/close and a periodic uplink. Decode open/closed, open count, last open duration, battery. Alarm if open longer than a configurable time.
- **Tank level (ultrasonic, e.g. Milesight EM500-UDL or Dragino DDS75-LB):** the sensor reports distance. Convert to level % and litres from device config (tank height, sensor offset, capacity). Keep the raw distance so it can be recalibrated.

### Alerts (scheduled job)
- Low tank level, low battery.
- No uplink for 2-3x the expected interval (dead sensor or gateway).
- Gate open longer than X minutes.
Use the app's existing notification mechanism.

### UI
- Device card per property (latest value, sparkline, battery, signal quality).
- Pins on the existing Leaflet map.

### Progress (2026-10-26)
Done: steps 1 and 2 of Phase 2 (backend, plus Manage -> Sensors with the inspector). Not done yet: device cards and map pins (UI above) and alerts.

Built differently from the plan above:
- **Every decoded field is stored**, one `sensor_readings` row per field. `devices.config.shown_metrics` picks which ones to show (defaults come from `App\Services\Lorawan\DeviceModels`). New sensor types need a decoder class plus a `DeviceModels` entry; then `php artisan lorawan:reprocess` decodes anything they sent earlier.
- `devices.type` became `model` (e.g. `dragino_lds02`), because the decoder depends on the hardware model, not on what the sensor is used for. The device's purpose goes in its name.
- No `app_key` column. The gateway's network server does the LoRaWAN crypto, so the app never needs the key, and storing a secret nothing uses only adds risk.
- Unique index is (device_id, f_cnt, recorded_at, metric), not (device_id, f_cnt). f_cnt restarts at 0 when a device rejoins. A gateway retry repeats both f_cnt and the gateway timestamp.
- Processing runs through Laravel's `defer()` after the response is sent, not a queued job. No queue worker is running in production.
- `lorawan_events` gained `dev_eui`, `processed_at` and `error`. Uplinks from unregistered DevEUIs are kept unprocessed (and exempt from `lorawan:prune`, which runs daily with 30-day retention). The Sensors page lists them as "Unrecognised devices" for app admins only, because gateway events aren't tied to a property.

## Security notes
- Shared-secret header only; endpoint is outside session auth and CSRF.
- Rate limit; validate event names; never log the secret or AppKeys.
- Only point the gateway at servers we control (it sends the secret header on every request).

## Open items
- Door sensor not yet delivered (order the **LDS02-AU915** version, 2 x AAA batteries).
- ~~Confirm the domain is publicly reachable over HTTPS~~ Done (2026-10-19): `https://fieldwerkz.com` is reachable. Use it rather than `www`, which redirects.
- Check the sensor's AU915 sub-band matches the gateway's channels before testing.
- Take a gateway backup after each configuration change.

## Captured samples

### Door sensor (Dragino LDS02), 2026-10-26
The first two events stored on production, as the gateway sent them (no keys included). The gateway is a RAK device with built-in network server, using the ChirpStack v3-style HTTP integration: `data` is a hex string (`data_encode: "hexstring"`), `timestamp` is epoch seconds, and `devEUI` is lowercase.

```json
{
    "adr": true,
    "data": "8C960100000800000000",
    "fCnt": 9,
    "fPort": 10,
    "devEUI": "a84041210862a0ce",
    "rxInfo": [
        {
            "rssi": -57,
            "loRaSNR": 12,
            "location": { "altitude": 0, "latitude": 0, "longitude": 0 },
            "gatewayID": "ac1f09fffe291787"
        }
    ],
    "txInfo": { "dr": 5, "frequency": 917200000 },
    "timestamp": 1792998613,
    "deviceName": "door1",
    "data_encode": "hexstring",
    "applicationID": "1",
    "applicationName": "Farm_Sensors"
}
```

The second event, 12 seconds later, has the same shape: `data` `0C9C0100000800000000`, `fCnt` 10, rssi -50, SNR 14.2, frequency 916800000, timestamp 1792998625.

Decoded with the LDS02 fPort 10 layout (10 bytes; confirm against Dragino's published decoder before relying on it):

| Bytes | Field | `8C96 01 000008 000000 00` | `0C9C 01 000008 000000 00` |
|---|---|---|---|
| 0-1, bit 15 | Door status (1 = open) | open | closed |
| 0-1, bits 13-0 | Battery (mV) | 3222 | 3228 |
| 2 | Mode | 1 | 1 |
| 3-5 | Total open count | 8 | 8 |
| 6-8 | Last open duration (min) | 0 | 0 |
| 9, bit 0 | Alarm | 0 | 0 |

That fits an open followed by a close 12 seconds later (under a minute, so the duration rounds to 0).
