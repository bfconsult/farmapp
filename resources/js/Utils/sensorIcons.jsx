// Map/list icons for sensors, one per kind and state (the kinds and their
// states are App\Services\Lorawan\SensorKinds). Plain SVG strings so the
// same icon works as a Leaflet divIcon and inline in React.

const COLORS = {
    attention: '#d97706',
    active: '#2563eb',
    idle: '#4b5563',
    normal: '#16a34a',
    unknown: '#9ca3af',
};

// White line drawings on a 24x24 grid.
const GLYPHS = {
    // A five-bar gate shut between its posts.
    'gate-closed': '<path d="M3 5v15M21 5v15M3 8h18M3 12h18M3 16h18M3 16L21 8"/>',
    // Swung back on its hinge post, the far post standing alone.
    'gate-open': '<path d="M3 5v15M21 5v15M3 8l8-3M3 12l8-2M3 16l8-1M11 5v10"/>',
    // A pump's impeller in its housing, with the outlet pipe.
    pump: '<circle cx="11" cy="12" r="7"/><path d="M11 12V7M11 12l4.3 2.5M11 12l-4.3 2.5M18 9h4"/>',
};

const STYLES = {
    'gate-closed': { glyph: 'gate-closed', color: COLORS.normal },
    'gate-open': { glyph: 'gate-open', color: COLORS.attention },
    'gate-unknown': { glyph: 'gate-closed', color: COLORS.unknown },
    'pump-running': { glyph: 'pump', color: COLORS.active },
    'pump-stopped': { glyph: 'pump', color: COLORS.idle },
    'pump-unknown': { glyph: 'pump', color: COLORS.unknown },
};

export function sensorIconSvg(kind, stateKey, size = 34) {
    const style = STYLES[`${kind}-${stateKey ?? 'unknown'}`] ?? STYLES[`${kind}-unknown`] ?? { glyph: null, color: COLORS.unknown };
    const glyph = GLYPHS[style.glyph] ?? '<circle cx="12" cy="12" r="3"/>';

    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="-6 -6 36 36" style="display:block">`
        + `<circle cx="12" cy="12" r="17" fill="${style.color}" stroke="#fff" stroke-width="2.5"/>`
        + `<g fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${glyph}</g>`
        + '</svg>';
}

export function SensorStateIcon({ kind, state, size = 28 }) {
    return (
        <span
            className="inline-block flex-shrink-0"
            style={{ width: size, height: size }}
            dangerouslySetInnerHTML={{ __html: sensorIconSvg(kind, state?.key, size) }}
        />
    );
}
