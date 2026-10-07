<?php

namespace Src\Setting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** The admin panel's default language. An admin's own choice (users.locale) wins over it. */
class LanguageSetting extends Model
{
    /** @var array<string, array{name: string, short: string}> the languages the panel offers, by locale code */
    public const LOCALES = [
        'en' => ['name' => 'English', 'short' => 'EN'],
        'km' => ['name' => 'ខ្មែរ', 'short' => 'KH'],
    ];

    protected $fillable = ['locale'];

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::LOCALES);
    }

    /** The saved default, or the app's own locale while nothing is saved yet (the table does not exist before migrating). */
    public static function defaultLocale(): string
    {
        try {
            $locale = static::query()->value('locale');
        } catch (QueryException) {
            $locale = null;
        }

        return self::isSupported($locale) ? $locale : config('app.locale');
    }
}
