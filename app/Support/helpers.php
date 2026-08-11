<?php

if (!function_exists('format_number')) {
    /**
     * Formats a number for display, dropping decimal places that aren't
     * there - 16.00 shows as "16", 16.50 shows as "16.5". Rounds to
     * $decimals places first (default 2), mirroring the frontend's
     * resources/js/numberFormat.js so exports/emails and the app agree.
     */
    function format_number($value, int $decimals = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return rtrim(rtrim(number_format((float) $value, $decimals, '.', ''), '0'), '.') ?: '0';
    }
}
