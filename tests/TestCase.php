<?php

namespace MrNewport\LaravelStow\Tests;

use MrNewport\LaravelStow\Providers\StowProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
        $this->artisan('migrate', ['--database' => 'testbench'])->run();

    }

    protected function getPackageProviders($app)
    {
        return [
            StowProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
