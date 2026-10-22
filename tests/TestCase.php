<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase runs migrate:fresh on whatever connection is configured. phpunit.xml points that at in-memory
     * SQLite, but a cached config (bootstrap/cache/config.php, made by `php artisan config:cache` or `optimize`)
     * makes Laravel ignore it and use the real MySQL database from .env — which wiped the local e_radio data on
     * 2026-10-07 and again on 2026-10-22. Refuse to go any further unless the tests are on SQLite.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');

        if (config("database.connections.{$connection}.driver") !== 'sqlite') {
            throw new RuntimeException(
                "Refusing to run the tests on the \"{$connection}\" database connection: they would wipe it. "
                .'Run `php artisan config:clear` first (a cached config ignores phpunit.xml), then try again.'
            );
        }

        return parent::setUpTraits();
    }
}
