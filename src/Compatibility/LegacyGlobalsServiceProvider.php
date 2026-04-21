<?php

declare(strict_types=1);

namespace Sloth\Compatibility;

use Sloth\Core\ServiceProvider;

/**
 * Legacy Globals Service Provider
 *
 * Registers deprecated $GLOBALS proxies for backwards compatibility
 * with themes that access Sloth via $GLOBALS['sloth'] or
 * $GLOBALS['sloth::plugin'].
 *
 * ## Migration
 *
 * | Legacy                                    | New                          |
 * |-------------------------------------------|------------------------------|
 * | $GLOBALS['sloth']                         | app()                        |
 * | $GLOBALS['sloth::plugin']->getContext()   | app('context')->getContext() |
 * | $GLOBALS['sloth::plugin']->isDevEnv()     | app()->isLocal()             |
 *
 * This provider can be removed once all themes have migrated.
 * See MIGRATE.md for the full migration guide.
 *
 * @since 1.0.0
 * @deprecated Will be removed in a future major version.
 */
class LegacyGlobalsServiceProvider extends ServiceProvider
{
    /**
     * Register the legacy $GLOBALS deprecation proxies.
     *
     * Both keys point to the same proxy object which delegates
     * all property and method access to the Application container
     * via app(), with a deprecation notice on each call.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $proxy = $this->makeProxy();

        $GLOBALS['sloth'] = $proxy;
        $GLOBALS['sloth::plugin'] = $proxy;
    }

    /**
     * Create a lazy deprecation proxy for $GLOBALS access.
     *
     * The proxy resolves app() at access time — not at registration time —
     * so it is safe to register before the application is fully booted.
     *
     * @since 1.0.0
     */
    private function makeProxy(): object
    {
        return new class {
            /**
             * Proxy a property access to the Application container.
             *
             * @param string $key The property name.
             * @return mixed
             */
            public function __get(string $key): mixed
            {
                trigger_error(
                    "\$GLOBALS['sloth']->{$key} is deprecated. Use app()->{$key} instead.",
                    E_USER_DEPRECATED
                );
                return app()->$key;
            }

            /**
             * Proxy a method call to the Application container.
             *
             * @param string $method The method name.
             * @param array $args The method arguments.
             * @return mixed
             */
            public function __call(string $method, array $args): mixed
            {
                trigger_error(
                    "\$GLOBALS['sloth']->{$method}() is deprecated. Use app()->{$method}() instead.",
                    E_USER_DEPRECATED
                );
                return app()->$method(...$args);
            }
        };
    }
}
