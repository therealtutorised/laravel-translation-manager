<?php

namespace HighSolutions\TranslationManager\Tests;

use HighSolutions\TranslationManager\ManagerServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base class for the package's tests: boots the package inside a Testbench
 * application. The database is MySQL, set in phpunit.xml(.dist).
 */
abstract class TestCase extends BaseTestCase
{

    protected function getPackageProviders($app)
    {
        return [
            ManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // The web middleware encrypts cookies, which needs an app key.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

}
