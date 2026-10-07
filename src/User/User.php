<?php

namespace Src\User;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Src\UserManagement\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password', 'is_active', 'locale', 'first_name', 'last_name', 'phone_number', 'address', 'avatar_url'];

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

    /** The profile photo as something an <img> can load: an uploaded file (kept on the public disk) or an external URL. */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(get: function () {
            $stored = $this->getRawOriginal('avatar_url');

            if (blank($stored)) {
                return null;
            }

            return str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://') ? $stored : MediaStorage::url($stored);
        });
    }
}
