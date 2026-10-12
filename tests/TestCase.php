<?php

namespace Tests;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $configuration = $application['config'];
        $connection = $configuration->get('database.default');
        $resolved = (new ConfigurationUrlParser)->parseConfiguration($configuration->get('database.connections.'.$connection, []));
        // Runs before RefreshDatabase can execute any schema or data writes.
        if (! $application->environment('testing') || ($resolved['driver'] ?? null) !== 'sqlite' || ($resolved['database'] ?? null) !== ':memory:') {
            throw new \RuntimeException('Tests require isolated in-memory SQLite. Clear configuration cache and check phpunit.xml. No persistent database is permitted.');
        }

        return $application;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
