// Display helpers for LoRaWAN sensor readings (Manage -> Sensors). A metric
// definition comes from App\Services\Lorawan\DeviceModels: {label, unit,
// states} - states turns a 0/1 value into a word like "Open".

export function formatReading(value, { unit, states } = {}) {
    if (value === null || value === undefined) {
        return '—';
    }
    if (states && states[value] !== undefined) {
        return states[value];
    }
    const number = Number.isInteger(value) ? value : Number(value.toFixed(3));

    return unit ? `${number} ${unit}` : `${number}`;
}

export function timeAgo(datetime) {
    if (!datetime) {
        return 'never';
    }
    const seconds = Math.round((Date.now() - new Date(datetime).getTime()) / 1000);
    if (seconds < 60) {
        return 'just now';
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min ago`;
    }
    const hours = Math.round(minutes / 60);
    if (hours < 48) {
        return `${hours} h ago`;
    }

    return `${Math.round(hours / 24)} days ago`;
}

export function formatDateTime(datetime) {
    return new Date(datetime).toLocaleString([], {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

// RSSI/SNR to a plain word. LoRa still decodes well below 0 dB SNR, so
// these are generous.
export function signalLabel(rssi, snr) {
    if (rssi === null || rssi === undefined) {
        return null;
    }
    const quality = rssi > -100 && (snr ?? 0) > 0 ? 'Good' : rssi > -115 ? 'Fair' : 'Weak';

    return `${quality} (${rssi} dBm${snr !== null && snr !== undefined ? `, SNR ${snr}` : ''})`;
}
