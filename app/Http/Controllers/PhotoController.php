<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\FarmJob;
use App\Models\MetricMeasurement;
use App\Models\Photo;
use App\Models\WorkSession;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class PhotoController extends Controller
{
    /**
     * Photos are resized to fit within this many pixels (longest side) before
     * storage, since phone cameras routinely produce multi-megabyte originals.
     */
    private const MAX_DIMENSION = 1920;

    private const JPEG_QUALITY = 80;

    public function store(Request $request, FarmJob $farmJob)
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'image|max:10240',
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $this->storeCompressed($file);

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
            $path = $this->storeCompressed($file);

            $workSession->photos()->create([
                'file' => $path,
                'time_taken' => now(),
                'location' => $request->location ?? null,
            ]);
        }

        return back();
    }

    public function storeForMetricMeasurement(Request $request, MetricMeasurement $metricMeasurement)
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'image|max:10240',
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $this->storeCompressed($file);

            $metricMeasurement->photos()->create([
                'file' => $path,
                'time_taken' => now(),
                'location' => $request->location ?? null,
            ]);
        }

        return back();
    }

    public function storeForChecklistItem(Request $request, ChecklistItem $checklistItem)
    {
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'image|max:10240',
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $this->storeCompressed($file);

            $checklistItem->photos()->create([
                'file' => $path,
                'time_taken' => now(),
                'location' => $request->location ?? null,
            ]);
        }

        return back();
    }

    public function destroy(Photo $photo)
    {
        Storage::disk(config('filesystems.default'))->delete($photo->file);
        $photo->delete();

        return back();
    }

    /**
     * Resize the uploaded photo down to a manageable size and re-encode it as
     * a JPEG, then store it. Returns the stored path.
     */
    private function storeCompressed(UploadedFile $file): string
    {
        $image = (new ImageManager(new Driver()))
            ->decode($file->getRealPath())
            ->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);

        $encoded = $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $path = 'photos/'.Str::uuid().'.jpg';

        Storage::disk(config('filesystems.default'))->put($path, (string) $encoded);

        return $path;
    }
}
