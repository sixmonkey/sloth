<?php

declare(strict_types=1);
namespace Sloth\Core\Traits;

/**
 * Environment detection and .env loading for the Application.
 *
 * Provides environment-aware helpers used throughout the framework.
 *
 * - load and cache environment: loadEnvironment()
 * - environment queries:       isLocal(), isProduction(), runningUnitTests(), environment()
 *
 * @since 2.0.0
 */
trait ApplicationEnvironmentTrait
{
    // -------------------------------------------------------------------------
    // Environment
    // -------------------------------------------------------------------------

    /**
     * Load environment variables from .env if present.
     *
     * Silently skips if no .env file exists — this is intentional.
     * The developer is responsible for ensuring the required variables
     * are set through other means (e.g. server environment, wp-config.php).
     *
     * No variables are marked as required here — that is too opinionated
     * for a framework. Validate required variables in your own bootstrap
     * if needed.
     *
     * @since 1.0.0
     */
    private function loadEnvironment(): void
    {
        $dir = $this->guessBasePath();

        while ($dir !== '/') {
            if (file_exists($dir . '/.env')) {
                \Dotenv\Dotenv::createImmutable($dir)->load();

                return;
            }

            $dir = dirname((string) $dir);
        }
    }

    /**
     * Check if running in a local/development environment.
     *
     * Matches WP_ENV values: 'development', 'develop', 'dev'.
     *
     * @since 1.0.0
     */
    public function isLocal(): bool
    {
        return in_array(env('WP_ENV', 'production'), ['local', 'development', 'develop', 'dev'], true);
    }

    /**
     * Check if running in production.
     *
     * @since 1.0.0
     */
    public function isProduction(): bool
    {
        return !$this->isLocal();
    }

    /**
     * Check if running unit tests.
     *
     * @since 1.0.0
     */
    public function runningUnitTests(): bool
    {
        return defined('WP_TESTS_PHASE') || env('WP_ENV') === 'testing';
    }

    /**
     * Get the current environment name.
     *
     * Returns the value of WP_ENV, defaulting to 'production'.
     *
     * @since 1.0.0
     */
    public function environment(): string
    {
        return env('WP_ENV', 'production');
    }
}
