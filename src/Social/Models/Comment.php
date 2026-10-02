<?php

namespace Src\Social\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Src\CmsAdmin\Models\User;

/** A comment on a station or media-content item — see morph map in SocialServiceProvider. */
class Comment extends Model
{
    protected $fillable = ['commentable_type', 'commentable_id', 'user_id', 'body'];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
