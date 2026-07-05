// Converts an ISO datetime string (UTC, as returned by the server) into the
// "YYYY-MM-DDTHH:mm" value a <input type="datetime-local"> expects, expressed
// in the browser's local timezone rather than UTC.
export function toLocalInputValue(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const localOffsetMs = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - localOffsetMs).toISOString().slice(0, 16);
}

// Converts a <input type="datetime-local"> value (interpreted by the browser
// as local time) into a UTC ISO string suitable for sending to the server.
export function fromLocalInputValue(localValue) {
    if (!localValue) return null;
    return new Date(localValue).toISOString();
}
