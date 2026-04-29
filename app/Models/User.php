<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_EXECUTIVE = 'executive';
    public const ROLE_CONTENT_MODERATOR = 'content_moderator';
    public const ROLE_EVENT_MANAGER = 'event_manager';
    public const ROLE_RUNNER = 'runner';
    public const ROLE_LEGACY_ADMIN = 'admin';

    protected $fillable = [
        'name',
        'email',
        'username',
        'avatar_path',
        'api_token',
        'api_token_expires_at',
        'password',
        'role',
        'phone',
        'gender',
        'birthdate',
        'address',
        'emergency_contact_name',
        'emergency_contact_number',
        'medical_conditions',
        'interests',
        'emergency_contact',
        'suspended_at',
        'banned_at',
        'last_login_at',
        'last_login_ip',
        'two_factor_required',
    ];

    protected $hidden = [
        'password',
        'api_token',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'api_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'interests' => 'array',
            'suspended_at' => 'datetime',
            'banned_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_required' => 'boolean',
        ];
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function raceResults()
    {
        return $this->hasMany(RaceResult::class);
    }

    public function managedEvents()
    {
        return $this->hasMany(Event::class, 'manager_id');
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
           ->filter()
           ->map(fn ($word) => strtoupper(substr($word, 0, 1)))
           ->take(2)
           ->implode('');
    }

    public function adminActivityLogs()
    {
        return $this->hasMany(AdminActivityLog::class);
    }

    public function communityPosts()
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function isAdmin()
    {
        return in_array($this->normalizedRole(), static::adminWebRoles());
    }

    public function isSuperAdmin()
    {
        return $this->normalizedRole() === static::ROLE_SUPER_ADMIN;
    }

    public function normalizedRole(): string
    {
        return match ($this->role) {
            static::ROLE_LEGACY_ADMIN => static::ROLE_EVENT_MANAGER,
            'user' => static::ROLE_RUNNER,
            default => $this->role,
        };
    }

    public static function adminWebRoles(): array
    {
        return [
            static::ROLE_SUPER_ADMIN,
            static::ROLE_EXECUTIVE,
            static::ROLE_CONTENT_MODERATOR,
            static::ROLE_EVENT_MANAGER,
        ];
    }

    public static function storedAdminRoles(): array
    {
        return [
            ...static::adminWebRoles(),
            static::ROLE_LEGACY_ADMIN,
        ];
    }

    public static function manageableRoles(): array
    {
        return [
            static::ROLE_RUNNER,
            static::ROLE_SUPER_ADMIN,
            static::ROLE_EXECUTIVE,
            static::ROLE_CONTENT_MODERATOR,
            static::ROLE_EVENT_MANAGER,
        ];
    }

    public static function roleLabels(): array
    {
        return [
            static::ROLE_RUNNER => 'Runner',
            static::ROLE_SUPER_ADMIN => 'Super Admin',
            static::ROLE_EXECUTIVE => 'Executive (CEO/COO)',
            static::ROLE_CONTENT_MODERATOR => 'Content Moderator',
            static::ROLE_EVENT_MANAGER => 'Event Manager',
            static::ROLE_LEGACY_ADMIN => 'Event Manager',
            'user' => 'Runner',
        ];
    }

    public function roleLabel(): string
    {
        return static::roleLabels()[$this->role] ?? str($this->normalizedRole())->replace('_', ' ')->title();
    }

    public function hasAdminRole(array|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return in_array($this->normalizedRole(), $roles, true);
    }

    public function canAccessAdminSection(string $section): bool
    {
        $role = $this->normalizedRole();

        $matrix = [
            'dashboard' => static::adminWebRoles(),
            'analytics' => [static::ROLE_SUPER_ADMIN, static::ROLE_EXECUTIVE],
            'users' => [static::ROLE_SUPER_ADMIN],
            'notifications' => [static::ROLE_SUPER_ADMIN, static::ROLE_CONTENT_MODERATOR, static::ROLE_EVENT_MANAGER],
            'community' => [static::ROLE_SUPER_ADMIN, static::ROLE_CONTENT_MODERATOR],
            'training' => [static::ROLE_SUPER_ADMIN, static::ROLE_CONTENT_MODERATOR],
            'checkpoints' => [static::ROLE_SUPER_ADMIN, static::ROLE_EVENT_MANAGER],
            'security' => [static::ROLE_SUPER_ADMIN],
        ];

        return in_array($role, $matrix[$section] ?? [], true);
    }

    public function managesAssignedEventsOnly(): bool
    {
        return $this->normalizedRole() === static::ROLE_EVENT_MANAGER;
    }

    public function managedEventIds(): array
    {
        if (! $this->managesAssignedEventsOnly()) {
            return [];
        }

        return $this->managedEvents()->pluck('events.id')->all();
    }

    public function canManageEvent(Event $event): bool
    {
        if (! $this->managesAssignedEventsOnly()) {
            return $this->isAdmin();
        }

        return (int) $event->manager_id === (int) $this->id;
    }

    public function isSuspended()
    {
        return $this->suspended_at !== null;
    }

    public function isBanned()
    {
        return $this->banned_at !== null;
    }
}
