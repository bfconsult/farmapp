<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    const COMPLETE = 'complete';
    const NEEDS_REVIEW = 'needs_review';

    const STATUSES = [self::COMPLETE, self::NEEDS_REVIEW];

    protected $fillable = [
        'farm_job_id', 'supplier_id', 'created_by', 'quote_id',
        'name', 'date', 'description', 'amount', 'gst_inclusive', 'reimburse',
        'invoice_file', 'invoice_original_name', 'status',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'gst_inclusive' => 'boolean',
        'reimburse' => 'boolean',
    ];

    protected $appends = ['invoice_url'];

    public function farmJob()
    {
        return $this->belongsTo(FarmJob::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Set when this expense was submitted by a supplier via their
     * Request-an-Invoice share link, rather than entered in-app - see
     * SupplierExpenseController.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * A single invoice document (image or PDF) - separate from photos()
     * above, which is image-only and force-recompressed to JPEG. Mirrors
     * Photo::getUrlAttribute()'s signed-URL-on-S3-else-plain-disk-URL
     * pattern.
     */
    public function getInvoiceUrlAttribute()
    {
        if (!$this->invoice_file) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));

        return config('filesystems.default') === 's3'
            ? $disk->temporaryUrl($this->invoice_file, now()->addHour())
            : $disk->url($this->invoice_file);
    }
}
