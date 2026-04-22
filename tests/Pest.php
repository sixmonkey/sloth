<?php

declare(strict_types=1);

use Brain\Monkey;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\Schema;

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