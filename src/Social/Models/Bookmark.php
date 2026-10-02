<?php

namespace Src\Social\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Src\CmsAdmin\Models\User;

/** A user's saved station or media-content item — distinct from local-only favorites. */
class Bookmark extends Model
{
    protected $fillable = ['user_id', 'bookmarkable_type', 'bookmarkable_id'];

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
