<?php

namespace Src\CmsAdmin\Models;

use App\Support\AvatarUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Src\CmsAdmin\Notifications\AccountRecovery;
use Src\Social\Models\Bookmark;
use Src\Social\Models\Comment;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'first_name', 'last_name', 'username', 'email', 'address', 'phone_number',
        'password', 'google_id', 'avatar_url',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::addGlobalScope('customer', fn (Builder $query) => $query->where('type', 'customer'));
        static::creating(fn (self $user) => $user->type = 'customer');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->first_name.' '.$this->last_name),
        );
    }

    /** The stored path or external URL, resolved to something loadable; `getRawOriginal('avatar_url')` is the stored value. */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => AvatarUrl::from($value),
        );
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AccountRecovery($token));
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
