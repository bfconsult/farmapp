<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Valid billing block sizes, in minutes, that work session durations may
     * be rounded up to for billing purposes.
     */
    public const BILLING_BLOCK_OPTIONS = [1, 15, 30, 60];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'deleted',
        'billing_block_minutes',
        'hourly_rate',
        'current_property_id',
        'timezone',
        'claimed_at',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Not the raw storage path - lets the frontend always just render
     * avatar_url (null when no avatar is set) without knowing about disks.
     */
    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'claimed_at' => 'datetime',
            'password' => 'hashed',
            'deleted' => 'boolean',
            'app_admin' => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));

        // S3 buckets aren't necessarily public-readable, so use a signed URL
        // rather than assuming a public ACL/bucket policy is in place -
        // same reasoning as Photo::getUrlAttribute(), including checking the
        // driver rather than the disk name.
        return config('filesystems.disks.'.config('filesystems.default').'.driver') === 's3'
            ? $disk->temporaryUrl($this->avatar, now()->addHour())
            : $disk->url($this->avatar);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function properties()
    {
        return $this->belongsToMany(Property::class, 'roles')->withPivot('type')->withTimestamps();
    }

    public function farmJobs()
    {
        return $this->hasMany(FarmJob::class);
    }

    public function assignedJobs()
    {
        return $this->belongsToMany(FarmJob::class, 'farm_job_user', 'user_id', 'farm_job_id')->withTimestamps();
    }

    public function workSessions()
    {
        return $this->hasMany(WorkSession::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'created_by');
    }

    public function checklists()
    {
        return $this->hasMany(Checklist::class, 'created_by');
    }

    public function metrics()
    {
        return $this->hasMany(Metric::class, 'created_by');
    }

    /**
     * Properties this user has ever created - approximated as "properties
     * they currently hold the admin role on", since PropertyController::store()
     * makes the creator an admin immediately and there's no dedicated
     * creator column on properties. Not exact if admin access is later
     * granted to/revoked from someone else.
     */
    public function adminProperties()
    {
        return $this->belongsToMany(Property::class, 'roles')->wherePivot('type', Role::ADMIN);
    }

    /**
     * The timezone to render this user's times in server-side (PDF/Excel
     * exports, emails) - captured automatically from their browser via
     * CaptureUserTimezone. Falls back to the app's UTC default for a user
     * who hasn't loaded an authenticated page since that middleware shipped.
     */
    public function displayTimezone(): string
    {
        return $this->timezone ?? config('app.timezone');
    }

    /**
     * Whether this user has ever set their own password and logged in -
     * false for a "shell" record a manager/admin added directly (just to
     * log time against) that hasn't been invited/claimed yet.
     */
    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    /**
     * Users it's actually possible to email - excludes shell records with
     * no email on file and anyone who was added but never claimed their
     * account, for anything that emails users in bulk (digests, reminders).
     */
    public function scopeContactable($query)
    {
        return $query->whereNotNull('email')->whereNotNull('claimed_at');
    }

    public function roleOn(Property $property): ?string
    {
        return $this->roles()
            ->where('property_id', $property->id)
            ->value('type');
    }

    public function isAdminOn(Property $property): bool
    {
        return $this->roleOn($property) === Role::ADMIN;
    }

    public function isManagerOn(Property $property): bool
    {
        return in_array($this->roleOn($property), [Role::ADMIN, Role::MANAGER]);
    }

    public function isWorkerOn(Property $property): bool
    {
        return in_array($this->roleOn($property), [Role::ADMIN, Role::MANAGER, Role::WORKER]);
    }

    /**
     * Round the given time down to this user's billing block boundary
     * (e.g. with a 15-minute block, 2:07 becomes 2:00). Returns the time
     * unchanged if no billing block preference is set.
     */
    public function floorToBillingBlock(\Illuminate\Support\Carbon $time): \Illuminate\Support\Carbon
    {
        if (!$this->billing_block_minutes) {
            return $time;
        }

        // Blocks line up with the hour in the user's own timezone (offsets
        // like Adelaide's +10:30 aren't whole hours), whatever the time's in.
        $local = $time->clone()->setTimezone($this->displayTimezone());
        $flooredMinute = intdiv($local->minute, $this->billing_block_minutes) * $this->billing_block_minutes;

        return $local->setTime($local->hour, $flooredMinute, 0)->setTimezone($time->getTimezone());
    }

    /**
     * Round the given time up to this user's billing block boundary (2:07
     * becomes 2:15) - where a session stopped now ends, matching how its
     * duration is billed. A time already on a boundary stays put.
     */
    public function ceilToBillingBlock(\Illuminate\Support\Carbon $time): \Illuminate\Support\Carbon
    {
        $floored = $this->floorToBillingBlock($time);

        return $floored->lt($time) ? $floored->addMinutes($this->billing_block_minutes) : $floored;
    }

    /**
     * When a session started now begins: now, rounded down to the billing
     * block - but not before the end of a session this user has just
     * stopped (whose end was rounded up), so the two don't overlap.
     */
    public function startOfNewSession(): \Illuminate\Support\Carbon
    {
        $start = $this->floorToBillingBlock(now());
        $lastEnd = WorkSession::where('user_id', $this->id)
            ->where('started_at', '<=', now())
            ->where('ended_at', '>', $start)
            ->max('ended_at');

        return $lastEnd ? \Illuminate\Support\Carbon::parse($lastEnd) : $start;
    }
}
