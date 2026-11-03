<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /** Droits de gérant : le gérant et le super administrateur. */
    public function isManager(): bool
    {
        return $this->role === UserRole::Manager || $this->isAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAccountant(): bool
    {
        return $this->role === UserRole::Accountant;
    }

    /** Le super administrateur a tous les rôles. */
    public function hasRole(UserRole ...$roles): bool
    {
        return $this->isAdmin() || in_array($this->role, $roles, true);
    }

    /** Peut modifier ce compte : selon les rôles que son propre rôle permet d'attribuer. */
    public function canManage(User $other): bool
    {
        return in_array($other->role, $this->role->assignable(), true);
    }
}
