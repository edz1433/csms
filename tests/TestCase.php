<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety catch. RefreshDatabase runs migrate:fresh, which DROPS EVERY TABLE
     * in whatever database is configured — so a test run pointed at the working
     * database destroys it. phpunit.xml forces DB_DATABASE=cpsu_csms_test, but
     * that is a config file anyone can break; this refuses to run at all unless
     * the connection really is a _test database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.'.config('database.default').'.database');
        $name = is_string($database) ? basename(str_replace(chr(92), '/', $database)) : '';

        if ($name !== ':memory:' && ! str_ends_with($name, '_test') && ! str_ends_with($name, '_testing')) {
            throw new RuntimeException(
                'Refusing to run tests against "'.$name.'": it is not a test database. '
                .'Tests wipe every table — point DB_DATABASE at a *_test database (see phpunit.xml).'
            );
        }
    }
}
