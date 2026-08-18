<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'administrator';
    public const ROLE_SUPPLY = 'supply_staff';
    public const ROLE_ACCOUNTING = 'accounting_staff';

    /** The one module Supply Staff never reach. */
    public const PAGE_USERS = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'role',
        'access',
        'is_active',
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
            'access' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAccountingStaff(): bool
    {
        return $this->role === self::ROLE_ACCOUNTING;
    }

    public function isSupplyStaff(): bool
    {
        return $this->role === self::ROLE_SUPPLY;
    }

    /**
     * Whether the user can see a given page key.
     *
     * Administrators always pass. Supply Staff work at the same level as an
     * administrator except for User Management, which stays administrator-only
     * — their per-page access array is not consulted. Everyone else (accounting)
     * needs the key in their access array.
     */
    public function canAccess(string $pageKey): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        if ($this->isSupplyStaff()) {
            return $pageKey !== self::PAGE_USERS;
        }

        return in_array($pageKey, $this->access ?? [], true);
    }

    /**
     * Whether page-level access is decided by role rather than by the
     * per-account checkboxes. Used by User Management to explain itself.
     */
    public function hasRoleBasedAccess(): bool
    {
        return $this->isAdministrator() || $this->isSupplyStaff();
    }

    /**
     * May create/edit/delete records. Accounting Staff is view-only everywhere
     * (bar the payment tag), so everyone else writes.
     */
    public function canWrite(): bool
    {
        return $this->isAdministrator() || $this->isSupplyStaff();
    }

    /**
     * First page key the user is allowed to land on (used post-login).
     */
    public function firstAccessiblePage(): string
    {
        if ($this->isAdministrator() || $this->canAccess('dashboard')) {
            return 'dashboard';
        }

        foreach (config('access.pages', []) as $key) {
            if ($this->canAccess($key)) {
                return $key;
            }
        }

        return 'dashboard';
    }
}
