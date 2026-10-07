<?php

namespace Src\Setting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** Where the sidebar's "Radio Studio" link goes — the radio studio is a separate site. Edited under Settings → Radio Studio. */
class RadioStudioSetting extends Model
{
    /** The address used until one is saved (and while the table does not exist yet, before migrating). */
    public const DEFAULT_URL = 'http://studio.devams.site/dashboard';

    protected $fillable = ['url'];

    /**
     * The address the link opens: the saved one, the default while nothing is saved, or null when an admin has
     * cleared it on purpose — then the link is not shown at all.
     */
    public static function studioUrl(): ?string
    {
        try {
            $setting = static::query()->first();
        } catch (QueryException) {
            return self::DEFAULT_URL;
        }

        return $setting === null ? self::DEFAULT_URL : (filled($setting->url) ? $setting->url : null);
    }
}
