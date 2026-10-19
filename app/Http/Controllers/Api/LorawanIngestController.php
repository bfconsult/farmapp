<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LorawanEvent;
use Illuminate\Http\Request;

class LorawanIngestController extends Controller
{
    /**
     * Phase 1: store the gateway's JSON exactly as received, so later
     * decoding is built from real payloads rather than guessed field names
     * (docs/lorawan-ingest-spec.md).
     */
    public function store(Request $request, string $event)
    {
        LorawanEvent::create([
            'event' => $event,
            'body' => $request->json()->all(),
        ]);

        return response()->noContent();
    }
}
