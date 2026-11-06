<?php

declare(strict_types=1);

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\Permission;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Username;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $username
 * @property string|null $email Hidden and unused since ADR-0009; kept until a later cleanup.
 * @property Role $role
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected $fillable = ['name', 'username', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token', 'email'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->is_active && $this->role->allows($permission);
    }

    /**
     * Stored normalised, so sign-in and uniqueness are case-insensitive. Set-only: reads return the stored value.
     *
     * @return Attribute<never, string>
     */
    protected function username(): Attribute
    {
        return Attribute::make(set: static fn (string $value): string => Username::normalize($value));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }
}
