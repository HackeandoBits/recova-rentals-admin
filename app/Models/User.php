<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Filament\Models\Contracts\FilamentUser;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    const ROLE_ADMIN = 'admin';

    const ROLE_USER = 'user';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'dni',
        'whatsapp',
        'avatar_url',
        'settings',
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
            'settings' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Todos los usuarios registrados pueden entrar al panel,
        // pero sus permisos estarán limitados por Policies.
        return true;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function googleToken(): HasOne
    {
        return $this->hasOne(GoogleToken::class, 'user_id', 'id');
    }
}
