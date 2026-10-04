<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case.
 *
 * The project had no tests/ directory at all before v13 — phpunit.xml pointed
 * at tests/Unit and tests/Feature and neither existed. This is the minimum
 * scaffolding to make `php artisan test` work.
 *
 * v19: rent now runs on a calendar (raised on the 1st, due on the 10th), so
 * some answers depend on what day "today" is. Run the suite as if it were
 * another day to check nothing is date-sensitive by accident:
 *
 *   FAKE_NOW=2026-10-17 php artisan test
 *
 * Tests that need a specific day pin it themselves with Carbon::setTestNow().
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if ($fake = getenv('FAKE_NOW')) {
            \Carbon\Carbon::setTestNow($fake);
        }
    }
}
