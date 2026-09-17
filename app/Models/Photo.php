<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class Photo extends Model
{
    protected $fillable = ['job_id', 'work_session_id', 'metric_measurement_id', 'checklist_item_id', 'note_id', 'expense_id', 'livestock_id', 'file', 'time_taken', 'location'];

    protected $casts = [
        'time_taken' => 'datetime',
    ];

    protected $appends = ['url'];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class, 'job_id');
    }

    public function workSession()
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function metricMeasurement()
    {
        return $this->belongsTo(MetricMeasurement::class);
    }

    public function checklistItem()
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function livestock()
    {
        return $this->belongsTo(Livestock::class);
    }

    public function getUrlAttribute()
    {
        $disk = Storage::disk(config('filesystems.default'));

        // S3 buckets aren't necessarily public-readable, so use a signed URL
        // rather than assuming a public ACL/bucket policy is in place.
        return config('filesystems.default') === 's3'
            ? $disk->temporaryUrl($this->file, now()->addHour())
            : $disk->url($this->file);
    }

    /**
     * A ready-to-embed thumbnail for the diary PDF, as a base64 data URI.
     * dompdf doesn't support `object-fit`, so cropping to the thumbnail's
     * aspect ratio happens here instead, server-side, via Intervention's
     * cover() - the embedded image is then already the right shape and can
     * be dropped straight into a plain <img> at its natural size.
     *
     * This also means the photo never needs fetching over HTTP: no S3
     * round-trip (so no isRemoteEnabled), and no self-request back into the
     * app (which deadlocks against `php artisan serve`'s single worker in
     * local dev).
     */
    public function getPdfThumbnailAttribute()
    {
        $bytes = Storage::disk(config('filesystems.default'))->get($this->file);

        $image = (new ImageManager(new Driver()))
            ->decode($bytes)
            ->cover(220, 160);

        $encoded = $image->encode(new JpegEncoder(quality: 70));

        return 'data:image/jpeg;base64,' . base64_encode((string) $encoded);
    }
}