<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, Auditable;
    
    protected $auditCategory = 'user';
    protected $auditExcludeFields = ['password', 'remember_token'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }

    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    public function removeRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->detach($role->id);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Get devices accessible by this user
     * 
     * Returns CPE devices this user can access with role-based permissions.
     * 
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function devices()
    {
        return $this->belongsToMany(
            CpeDevice::class,
            'user_devices',
            'user_id',
            'cpe_device_id'
        )->withPivot('role', 'department')
          ->withTimestamps();
    }

    /**
     * Check if user has access to specific device
     * 
     * @param int|CpeDevice $device Device ID or model instance
     * @param string|null $minRole Minimum required role (viewer, manager, admin)
     * @return bool
     */
    public function canAccessDevice(int|CpeDevice $device, ?string $minRole = null): bool
    {
        $deviceId = $device instanceof CpeDevice ? $device->id : $device;
        
        $query = $this->devices()->where('cpe_device_id', $deviceId);
        
        if ($minRole) {
            // Role hierarchy: admin > manager > viewer
            $roleHierarchy = [
                'viewer' => ['viewer', 'manager', 'admin'],
                'manager' => ['manager', 'admin'],
                'admin' => ['admin']
            ];
            
            $allowedRoles = $roleHierarchy[$minRole] ?? [];
            $query->whereIn('user_devices.role', $allowedRoles);
        }
        
        return $query->exists();
    }

    /**
     * Get user's role for specific device
     * 
     * @param int|CpeDevice $device Device ID or model instance
     * @return string|null Role (viewer, manager, admin) or null if no access
     */
    public function getDeviceRole(int|CpeDevice $device): ?string
    {
        $deviceId = $device instanceof CpeDevice ? $device->id : $device;
        
        $pivot = $this->devices()
            ->where('cpe_device_id', $deviceId)
            ->first();
        
        return $pivot?->pivot->role;
    }
}
