<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any HTTP request a test did not explicitly fake fails the test:
        // the suite must never talk to real external services (backstopped
        // by the forced-empty ANTHROPIC_API_KEY in phpunit.xml).
        Http::preventStrayRequests();
    }
}
