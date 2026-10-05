<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @return array<string, string> */
    protected function spaHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Origin' => (string) config('app.frontend_url'),
            'Referer' => rtrim((string) config('app.frontend_url'), '/').'/',
        ];
    }
}
