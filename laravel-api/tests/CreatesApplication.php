<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
            throw new \RuntimeException('Tests require APP_ENV=testing.');
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        if ($app['config']['database.default'] !== 'sqlite' || $app['config']['database.connections.sqlite.database'] !== ':memory:') {
            throw new \RuntimeException('Tests require the dedicated in-memory SQLite database.');
        }

        return $app;
    }
}
