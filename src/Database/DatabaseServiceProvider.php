<?php

declare(strict_types=1);

namespace Sloth\Database;

use Corcel\Database;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the database connection.
 *
 * Establishes the Corcel/Eloquent connection to the WordPress database
 * and sets up the event dispatcher for Eloquent model events.
 *
 * ## Why Corcel
 *
 * Corcel wraps WordPress's database tables in Laravel's Eloquent ORM,
 * allowing Sloth models to use familiar Eloquent patterns while reading
 * from the standard WordPress schema.
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
        // Bind db prefix for use elsewhere in the framework
        $this->app->instance('db.prefix', DB_PREFIX);
    }

    /**
     * Establish the Corcel database connection.
     *
     * Connects to WordPress's database using the constants defined
     * in bootstrap.php (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD, DB_PREFIX).
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        Database::connect([
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix' => DB_PREFIX,
        ]);

        Model::setEventDispatcher(new Dispatcher($this->app));

        // Enable query logging for SlothBarPanel
        \Corcel\Model\Post::resolveConnection()->enableQueryLog();
    }
}
