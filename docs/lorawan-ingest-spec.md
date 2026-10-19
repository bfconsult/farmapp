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

Stop after Phase 1. Wait for a real uplink and paste its stored `body` into this file under "Captured samples" before starting Phase 2.

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
(Paste real uplink / join bodies here once the sensor reports. Remove any keys.)
