<?php

namespace Src\CmsAdmin\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** For forms where the slug can be left empty and is worked out from the name or title. */
trait GeneratesUniqueSlug
{
    /**
     * A slug from $source that no row in $table has yet: "my-show", then
     * "my-show-2", "my-show-3", … A name with no Latin letters or digits (a
     * Khmer title, say) slugs to nothing, so it falls back to $fallback.
     */
    private function uniqueSlug(string $source, string $table, string $fallback): string
    {
        $base = Str::slug($source) ?: $fallback;
        $slug = $base;

        for ($suffix = 2; DB::table($table)->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
