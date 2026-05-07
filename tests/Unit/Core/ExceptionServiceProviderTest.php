<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Core;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use PHPUnit\Framework\TestCase;
use Sloth\Core\ExceptionServiceProvider;
use Sloth\Exceptions\ExceptionHandler;

/**
 * Unit tests for the ExceptionServiceProvider class.
 *
 * These tests verify that the service provider correctly registers
 * the exception handler as a singleton in the container.
 *
 * @since 1.0.0
 */
class ExceptionServiceProviderTest extends TestCase
{
    /**
     * The application container instance.
     */
    protected Container $app;

    /**
     * Set up the test environment with a fresh container.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Container();
    }

    /**
     * Test that register() binds the ExceptionHandlerContract to ExceptionHandler.
     */
    public function test_register_binds_exception_handler_contract(): void
    {
        $provider = new ExceptionServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound(ExceptionHandlerContract::class));
    }

    /**
     * Test that the binding resolves to the correct class.
     */
    public function test_binding_resolves_to_exception_handler(): void
    {
        $provider = new ExceptionServiceProvider($this->app);
        $provider->register();

        $handler = $this->app->make(ExceptionHandlerContract::class);

        $this->assertInstanceOf(ExceptionHandler::class, $handler);
    }

    /**
     * Test that the handler is registered as a singleton.
     */
    public function test_handler_is_singleton(): void
    {
        $provider = new ExceptionServiceProvider($this->app);
        $provider->register();

        $instance1 = $this->app->make(ExceptionHandlerContract::class);
        $instance2 = $this->app->make(ExceptionHandlerContract::class);

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test that register() does not affect other bindings.
     */
    public function test_register_does_not_interfere_with_other_bindings(): void
    {
        $this->app->singleton('test-service', fn(): \stdClass => new \stdClass());

        $provider = new ExceptionServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('test-service'));
        $this->assertInstanceOf(\stdClass::class, $this->app->make('test-service'));
    }

    /**
     * Test that the provider can be instantiated with the app.
     */
    public function test_provider_can_be_instantiated(): void
    {
        $provider = new ExceptionServiceProvider($this->app);

        $this->assertInstanceOf(ExceptionServiceProvider::class, $provider);
    }

    /**
     * Test that native exception handler is NOT registered during tests.
     *
     * WP_TESTS_PHASE constant is defined in the test bootstrap, so
     * set_exception_handler() should not be called.
     */
    public function test_native_handler_is_skipped_in_tests(): void
    {
        $this->assertTrue(defined('WP_TESTS_PHASE'), 'WP_TESTS_PHASE should be defined');
        $this->assertTrue(WP_TESTS_PHASE, 'WP_TESTS_PHASE should be true');

        // If native handlers were registered, the global exception handler would
        // be our closure. Since we're in tests, it should NOT have been changed.
        $currentHandler = set_exception_handler(fn(): null => null);
        restore_exception_handler();

        // The current handler should be null or PHP's default, not our Sloth handler
        // (If it were our handler, it would be a Closure from registerExceptionHandler)
        $this->assertNotInstanceOf(
            \Closure::class,
            $currentHandler,
            'Native exception handler should not be registered during tests'
        );
    }

    /**
     * Test that registerExceptionHandler() method exists and is protected.
     */
    public function test_registerExceptionHandler_method_exists(): void
    {
        $provider = new ExceptionServiceProvider($this->app);
        $reflection = new \ReflectionClass($provider);

        $this->assertTrue($reflection->hasMethod('registerExceptionHandler'));
        $method = $reflection->getMethod('registerExceptionHandler');
        $this->assertTrue($method->isProtected());
    }
}
