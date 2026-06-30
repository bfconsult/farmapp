<?php

namespace App\Http\Controllers;

use App\Models\FarmJob;
use App\Models\Photo;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function store(Request $request, FarmJob $farmJob)
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'image|max:10240',
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $file->store('photos', 'public');

            $farmJob->photos()->create([
                'file' => $path,
                'time_taken' => now(),
                'location' => $request->location ?? null,
            ]);
        }

        return back();
    }

    public function storeForSession(Request $request, WorkSession $workSession)
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'image|max:10240',
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $file->store('photos', 'public');

            $workSession->photos()->create([
                'file' => $path,
                'time_taken' => now(),
                'location' => $request->location ?? null,
            ]);
        }

        return back();
    }

    public function destroy(Photo $photo)
    {
        Storage::disk('public')->delete($photo->file);
        $photo->delete();

        return back();
    }
}