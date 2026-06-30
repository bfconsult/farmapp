<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
}
