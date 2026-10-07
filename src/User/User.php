<?php

namespace Src\User;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Src\UserManagement\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'is_active', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::addGlobalScope('admin', fn (Builder $query) => $query->where('type', 'admin'));
        static::creating(fn (self $user) => $user->type = 'admin');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
