<?php

declare(strict_types=1);

namespace Sloth\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the database connection.
 *
 * Establishes Eloquent/Capsule connections and sets up the event
 * dispatcher for Eloquent model events.
 *
 * ## Configuration
 *
 * Connection parameters are read from the `database` config key.
 * The default `wordpress` connection resolves values in this order:
 *
 * 1. `.env` file (via env())
 * 2. WordPress constants (DB_HOST, DB_NAME, etc.) if defined
 * 3. Hardcoded defaults
 *
 * This means Sloth can boot without WordPress constants — useful
 * for CLI commands that only need the database.
 *
 * ## Multiple Connections
 *
 * Theme developers can add connections in app/config/database.php:
 *
 * ```php
 * return [
 *     'connections' => [
 *         'external' => [
 *             'driver'   => 'mysql',
 *             'host'     => env('EXTERNAL_DB_HOST'),
 *             'database' => env('EXTERNAL_DB_NAME'),
 *             'username' => env('EXTERNAL_DB_USER'),
 *             'password' => env('EXTERNAL_DB_PASSWORD'),
 *             'charset'  => 'utf8mb4',
 *             'collation'=> 'utf8mb4_unicode_ci',
 *             'prefix'   => '',
 *         ],
 *     ],
 * ];
 * ```
 *
 * Then use it in a model: `protected $connection = 'external';`
 *
 * ## Query Logging
 *
 * Query logging is enabled unconditionally so that QueryCollector can
 * display executed queries in the DebugBar during development.
 * In production the query collector is not loaded.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Debug\Collectors\QueryCollector
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register database bindings.
     *
     * Merges the framework database config and binds db.prefix
     * so other providers can access the table prefix early.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/database.php', 'database');

        $this->app->instance(
            'db.prefix',
            config('database.connections.' . config('database.default') . '.prefix', 'wp_')
        );
    }

    /**
     * Establish all configured Eloquent database connections.
     *
     * Iterates over database.connections and registers each with
     * Capsule. The default connection is set from database.default.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function boot(): void
    {
        $capsule = new Capsule();

        foreach (config('database.connections', []) as $name => $config) {
            $capsule->addConnection($config, $name);
        }

        $capsule->getDatabaseManager()->setDefaultConnection(
            config('database.default', 'wordpress')
        );

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Model::setEventDispatcher(new Dispatcher($this->app));

        Model::resolveConnection()->enableQueryLog();

        global $wpdb;
        if (isset($wpdb)) {
            $wpdb->save_queries = true;
        }
    }
}
