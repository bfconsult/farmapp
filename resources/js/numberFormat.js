/**
 * Formats a number for display, dropping decimal places that aren't there -
 * 16.00 shows as "16", 16.50 shows as "16.5", 16.25 stays "16.25". Rounds to
 * `decimals` places first (default 2), so anything with more precision than
 * that still gets a clean, consistent result rather than a long tail.
 *
 * Every money/hours figure in the app comes from either a `decimal(x,2)`
 * database column (which PDO/Eloquent returns as an already zero-padded
 * string like "16.00") or a rounded PHP float - this normalises both.
 */
export function formatNumber(value, decimals = 2) {
    if (value === null || value === undefined || value === '') return value;
    return Number(Number(value).toFixed(decimals));
}
