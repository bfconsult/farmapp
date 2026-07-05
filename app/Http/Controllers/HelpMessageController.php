<?php

namespace App\Http\Controllers;

use App\Models\HelpMessage;

class HelpMessageController extends Controller
{
    public function show(string $key)
    {
        $message = HelpMessage::where('key', $key)->first();

        if (!$message) {
            return response()->json(['title' => null, 'body' => 'No help available for this yet.'], 404);
        }

        return response()->json([
            'title' => $message->title,
            'body' => $message->body,
        ]);
    }
}
