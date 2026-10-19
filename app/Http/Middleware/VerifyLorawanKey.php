<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The LoRaWAN gateway authenticates with a shared secret in the
 * X-Fieldwerkz-Ingest-Key header (it can't hold a user session or token). Rejects
 * everything if the key isn't configured, rather than accepting an empty
 * header.
 */
class VerifyLorawanKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.lorawan.key');
        $given = (string) $request->header('X-Fieldwerkz-Ingest-Key');

        abort_if($expected === '' || !hash_equals($expected, $given), 401);

        return $next($request);
    }
}
