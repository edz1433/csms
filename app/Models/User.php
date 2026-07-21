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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
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
     * Administrators always pass; others need the key in their access array.
     */
    public function canAccess(string $pageKey): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        return in_array($pageKey, $this->access ?? [], true);
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
