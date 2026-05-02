<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Debug\CollectorProviders;

use DebugBar\DebugBar;
use PHPUnit\Framework\TestCase;
use Sloth\Debug\CollectorProviders\AbstractCollectorProvider;
use Sloth\Debug\CollectorProviders\MessageCollectorProvider;
use Sloth\Debug\CollectorProviders\PdoCollectorProvider;
use Sloth\Debug\CollectorProviders\QueryCollectorProvider;
use Sloth\Debug\CollectorProviders\SlothCollectorProvider;
use Sloth\Debug\CollectorProviders\AcfCollectorProvider;
use Sloth\Debug\CollectorProviders\WordpressCollectorProvider;
use Sloth\Debug\CollectorProviders\PhpInfoCollectorProvider;
use Sloth\Debug\CollectorProviders\MemoryCollectorProvider;

/**
 * Unit tests for all DebugBar CollectorProviders.
 *
 * Each CollectorProvider wraps a DebugBar and adds a specific
 * DataCollector during boot().
 *
 * @since 1.0.0
 */
class CollectorProvidersTest extends TestCase
{
    protected DebugBar $debugBar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->debugBar = new DebugBar();
    }

    public function test_abstract_collector_provider_is_abstract(): void
    {
        $reflection = new \ReflectionClass(AbstractCollectorProvider::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function test_message_collector_provider_extends_abstract(): void
    {
        $provider = new MessageCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_pdo_collector_provider_extends_abstract(): void
    {
        $provider = new PdoCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_query_collector_provider_extends_abstract(): void
    {
        $provider = new QueryCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_sloth_collector_provider_extends_abstract(): void
    {
        $provider = new SlothCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_acf_collector_provider_extends_abstract(): void
    {
        $provider = new AcfCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_wordpress_collector_provider_extends_abstract(): void
    {
        $provider = new WordpressCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_php_info_collector_provider_extends_abstract(): void
    {
        $provider = new PhpInfoCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_memory_collector_provider_extends_abstract(): void
    {
        $provider = new MemoryCollectorProvider($this->debugBar);
        $this->assertInstanceOf(AbstractCollectorProvider::class, $provider);
    }

    public function test_abstract_provider_has_collector_delegates_to_debug_bar(): void
    {
        $provider = new QueryCollectorProvider($this->debugBar);
        $this->assertFalse($provider->hasCollector('queries'));
    }

    public function test_query_collector_provider_adds_queries_collector_on_boot(): void
    {
        $provider = new QueryCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('queries'));
    }

    public function test_sloth_collector_provider_adds_sloth_collector_on_boot(): void
    {
        $provider = new SlothCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('sloth'));
    }

    public function test_acf_collector_provider_adds_acf_collector_on_boot(): void
    {
        $provider = new AcfCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('acf'));
    }

    public function test_wordpress_collector_provider_adds_wordpress_collector_on_boot(): void
    {
        $provider = new WordpressCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('wordpress'));
    }

    public function test_php_info_collector_provider_adds_phpinfo_collector_on_boot(): void
    {
        $provider = new PhpInfoCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('php'));
    }

    public function test_memory_collector_provider_adds_memory_collector_on_boot(): void
    {
        $provider = new MemoryCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('memory'));
    }

    public function test_pdo_collector_provider_adds_pdo_collector_on_boot(): void
    {
        $provider = new PdoCollectorProvider($this->debugBar);
        $provider->boot();

        $this->assertTrue($provider->hasCollector('pdo'));
    }

    public function test_message_collector_provider_has_critical_errors_constant(): void
    {
        $reflection = new \ReflectionClass(MessageCollectorProvider::class);
        $constant = $reflection->getConstant('CRITICAL_ERRORS');

        $this->assertIsInt($constant);
        $this->assertTrue((bool)($constant & E_ERROR));
        $this->assertTrue((bool)($constant & E_PARSE));
        $this->assertTrue((bool)($constant & E_CORE_ERROR));
        $this->assertTrue((bool)($constant & E_COMPILE_ERROR));
        $this->assertTrue((bool)($constant & E_USER_ERROR));
        $this->assertTrue((bool)($constant & E_RECOVERABLE_ERROR));
    }

    public function test_message_collector_provider_severity_labels(): void
    {
        $provider = new MessageCollectorProvider($this->debugBar);
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('severityLabel');
        $method->setAccessible(true);

        $this->assertEquals('warning', $method->invoke($provider, E_WARNING));
        $this->assertEquals('notice', $method->invoke($provider, E_NOTICE));
        $this->assertEquals('warning', $method->invoke($provider, E_USER_WARNING));
        $this->assertEquals('notice', $method->invoke($provider, E_USER_NOTICE));
        $this->assertEquals('info', $method->invoke($provider, E_DEPRECATED));
        $this->assertEquals('info', $method->invoke($provider, E_USER_DEPRECATED));
        $this->assertEquals('warning', $method->invoke($provider, 0));
    }

    public function test_message_collector_provider_registerErrorHandler_method_exists(): void
    {
        $provider = new MessageCollectorProvider($this->debugBar);
        $reflection = new \ReflectionClass($provider);

        $this->assertTrue($reflection->hasMethod('registerErrorHandler'));
        $method = $reflection->getMethod('registerErrorHandler');
        $this->assertTrue($method->isProtected());
    }
}
