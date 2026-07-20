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

// Splits a toLocalInputValue()-style "YYYY-MM-DDTHH:mm" string into separate
// date/time pieces for a pair of <input type="date">/<input type="time">
// fields - editing just the time (the common case) is otherwise awkward
// with a single combined datetime-local input.
export function splitLocalValue(localValue) {
    if (!localValue) return { date: '', time: '' };
    const [date, time] = localValue.split('T');
    return { date, time };
}

// The inverse of splitLocalValue - recombines separate date/time pieces back
// into a toLocalInputValue()-style string for fromLocalInputValue().
export function joinLocalValue(date, time) {
    if (!date || !time) return '';
    return `${date}T${time}`;
}

// Human label for a billing_block_minutes value (see User::BILLING_BLOCK_OPTIONS).
export function billingBlockLabel(minutes) {
    if (minutes === 60) return '1 hour';
    if (minutes === 1) return '1 minute';
    return `${minutes} minutes`;
}

// Every "HH:mm" time-of-day value at a billing_block_minutes granularity,
// for a <select> that can only ever choose a block-aligned time - unlike
// <input type="time" step>, whose step is only honoured by some browsers'
// arrow-key increments, not by the actual picker widget most show.
export function timeOptionsForBlock(blockMinutes) {
    const step = blockMinutes || 1;
    const options = [];
    for (let minutes = 0; minutes < 24 * 60; minutes += step) {
        const hour = Math.floor(minutes / 60);
        const minute = minutes % 60;
        options.push({
            value: `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`,
            label: new Date(2000, 0, 1, hour, minute).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }),
        });
    }
    return options;
}

const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_LONG = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const WEEKDAYS_LONG = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/**
 * Formats a date day-first ("17 Jul 26") - the app's house style, rather
 * than the American month-first convention `toLocaleDateString` defaults
 * to (its ordering is locale-dependent even with explicit field options,
 * so it can't be trusted to stay day-first). Accepts a Date, or anything
 * `new Date()` can parse (an ISO string, "YYYY-MM-DD", etc.) - a bare
 * "YYYY-MM-DD" is treated as UTC midnight per spec, which getDate()/etc.
 * then read back in local time, silently shifting to the previous day in
 * negative-UTC-offset timezones - so that case is coerced to local midnight
 * instead, same as every call site used to do by hand before this helper
 * existed.
 */
export function formatDate(input, { month = 'short', year = '2-digit', weekday = false } = {}) {
    const dateOnly = typeof input === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(input);
    const date = input instanceof Date ? input : new Date(dateOnly ? `${input}T00:00:00` : input);
    const monthStr = (month === 'long' ? MONTHS_LONG : MONTHS_SHORT)[date.getMonth()];
    const yearStr = year === false
        ? ''
        : ` ${year === 'numeric' ? date.getFullYear() : String(date.getFullYear()).slice(-2)}`;
    const dateStr = `${date.getDate()} ${monthStr}${yearStr}`;

    if (!weekday) return dateStr;

    const weekdayStr = (weekday === 'long' ? WEEKDAYS_LONG : WEEKDAYS_SHORT)[date.getDay()];
    return `${weekdayStr}, ${dateStr}`;
}
