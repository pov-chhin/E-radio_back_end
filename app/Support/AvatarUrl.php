<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class AvatarUrl
{
    /**
     * A profile picture is stored either as a path on the public disk (an upload through the app) or as a full
     * URL (a Google account's photo). Either way this returns something an <img> or the app can load directly.
     */
    public static function from(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://')
            ? $value
            : Storage::disk('public')->url($value);
    }
}
