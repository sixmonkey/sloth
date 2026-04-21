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
 * Establishes an Eloquent/Capsule connection to the WordPress database
 * and sets up the event dispatcher for Eloquent model events.
 *
 * Previously delegated to Corcel\Database::connect() — now owns the
 * Capsule setup directly, removing the last non-model dependency on Corcel.
 *
 * ## Query logging
 *
 * Query logging is enabled unconditionally so that SlothBarPanel can
 * display executed queries in the Tracy debug bar during development.
 * In production Tracy is silent, so the query log overhead is minimal.
 *
 * ## Migration path
 *
 * Long-term goal (Step 8): remove Corcel entirely and query WordPress
 * tables directly via Eloquent without the Corcel compatibility layer.
 * See REFACTOR.md Step 8 for details.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Debug\Panels\SlothBarPanel
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register database bindings.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->instance('db.prefix', DB_PREFIX);
    }

    /**
     * Establish the Eloquent database connection.
     *
     * Connects to WordPress's database using the constants defined
     * in bootstrap.php (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_PREFIX).
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $capsule = new Capsule();

        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => env('DB_HOST', DB_HOST),
            'database' => env('DB_NAME', DB_NAME),
            'username' => env('DB_USER', DB_USER),
            'password' => env('DB_PASSWORD', DB_PASSWORD),
            'charset' => env('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
            'collation' => env('DB_COLLATION', defined('DB_COLLATION') ? DB_COLLATION : 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', DB_PREFIX),
        ]);

        $capsule->bootEloquent();

        Model::setEventDispatcher(new Dispatcher($this->app));

        // Enable query logging for SlothBarPanel
        Model::resolveConnection()->enableQueryLog();
    }
}
