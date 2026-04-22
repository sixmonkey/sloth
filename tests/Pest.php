<?php

declare(strict_types=1);

// IMPORTANT: app() function must be defined FIRST in global namespace
// Corcel checks app()->version(), this stubs it for standalone testing
use Illuminate\Container\Container;

if (!function_exists('app')) {
    function app() {
        static $stub = null;
        if ($stub === null) {
            $stub = new class extends Container {
                public function version(): string
                {
                    return 'Laravel 11.0';
                }
            };
            Container::setInstance($stub);
        }
        return Container::getInstance();
    }
}

use Brain\Monkey;

beforeEach(function (): void {
    monkeySetUpDatabase();
});

afterEach(function (): void {
    monkeyTearDownDatabase();
});

function monkeySetUpDatabase(): void
{
    Monkey\setUp();

    $capsule = new Capsule();
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    runMigrations();
}

function monkeyTearDownDatabase(): void
{
    Schema::dropAllTables();
    Capsule::connection()->disconnect();
    Monkey\tearDown();
}

function runMigrations(): void
{
    $migrations = [
        new CreateSlothUsersTable(),
        new CreateSlothUsermetaTable(),
        new CreateSlothTermsTable(),
        new CreateSlothTermmetaTable(),
        new CreateSlothTermTaxonomyTable(),
        new CreateSlothTermRelationshipsTable(),
        new CreateSlothPostsTable(),
        new CreateSlothPostmetaTable(),
        new CreateSlothCommentsTable(),
        new CreateSlothCommentmetaTable(),
        new CreateSlothOptionsTable(),
    ];

    foreach ($migrations as $migration) {
        $migration->up();
    }
}