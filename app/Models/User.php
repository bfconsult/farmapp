<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted' => 'boolean',
        ];
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

        $flooredMinute = intdiv($time->minute, $this->billing_block_minutes) * $this->billing_block_minutes;

        return $time->clone()->setTime($time->hour, $flooredMinute, 0);
    }
}
