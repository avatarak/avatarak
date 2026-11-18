<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // views use @vite; tests must not depend on a built frontend
        $this->withoutVite();
        // most tests register users without a checkbox; the acceptance rule has its own tests
        config(['legal.require_acceptance' => false]);
    }
    //
}
