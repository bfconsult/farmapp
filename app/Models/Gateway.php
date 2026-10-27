<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A property's own LoRaWAN gateway. It authenticates to the ingest
 * endpoint with its key, which is how an event is tied to a property.
 * Only the key's hash is stored; the key is shown once, when issued.
 */
class Gateway extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'gateway_eui',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    public static function findByKey(string $key): ?self
    {
        return $key === '' ? null : static::where('key_hash', static::hashKey($key))->first();
    }

    /**
     * Replace the key (or set the first one) and return it - the only time
     * the plain key is available.
     */
    public function issueKey(): string
    {
        $key = 'fwz_'.Str::random(40);
        $this->forceFill(['key_hash' => static::hashKey($key)])->save();

        return $key;
    }
}
