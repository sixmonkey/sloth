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
        $this->app->singleton('test-service', fn () => new \stdClass());

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
}
