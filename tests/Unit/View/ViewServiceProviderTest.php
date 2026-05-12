<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\View;

use Illuminate\View\Factory;
use Sloth\View\Extensions\AbstractViewExtension;
use Sloth\View\ViewFinder;
use Sloth\View\ViewServiceProvider;

/**
 * Fixture extension A — registers 'conflict_helper'
 */
class ConflictExtensionA extends AbstractViewExtension
{
    #[\Override]
    public function getHelpers(): array
    {
        return ['conflict_helper' => fn($v): string => $v . '_a'];
    }
}

/**
 * Fixture extension B — also registers 'conflict_helper'
 */
class ConflictExtensionB extends AbstractViewExtension
{
    #[\Override]
    public function getHelpers(): array
    {
        return ['conflict_helper' => fn($v): string => $v . '_b'];
    }
}

/**
 * Tests for ViewServiceProvider.
 */
describe('ViewServiceProvider', function (): void {

    describe('register()', function (): void {
        it('binds view.finder in the container', function (): void {
            $app = makeTestApp();
            new ViewServiceProvider($app)->register();

            expect($app->bound('view.finder'))->toBeTrue();
            expect($app->make('view.finder'))->toBeInstanceOf(ViewFinder::class);
        });

        it('binds view in the container', function (): void {
            $app = makeTestApp();
            new ViewServiceProvider($app)->register();

            expect($app->bound('view'))->toBeTrue();
            expect($app->make('view'))->toBeInstanceOf(Factory::class);
        });

        it('binds view.engine.resolver in the container', function (): void {
            $app = makeTestApp();
            new ViewServiceProvider($app)->register();

            expect($app->bound('view.engine.resolver'))->toBeTrue();
        });

        it('binds twig via TwigAdapter', function (): void {
            $app = makeTestApp();
            new ViewServiceProvider($app)->register();

            expect($app->bound('twig'))->toBeTrue();
            expect($app->bound('twig.loader'))->toBeTrue();
        });
    });

    describe('boot()', function (): void {
        it('sets _helpers on the view factory', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);
            $provider->register();
            $provider->boot();

            $shared = $app['view']->getShared();
            expect($shared)->toHaveKey('_helpers');
            expect($shared['_helpers'])->toBeArray();
        });

        it('sets _directives on the view factory', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);
            $provider->register();
            $provider->boot();

            $shared = $app['view']->getShared();
            expect($shared)->toHaveKey('_directives');
            expect($shared['_directives'])->toBeArray();
        });

        it('includes SlothViewExtension helpers', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);
            $provider->register();
            $provider->boot();

            $helpers = $app['view']->getShared()['_helpers'];
            expect($helpers)->toHaveKey('debug');
            expect($helpers)->toHaveKey('tel');
            expect($helpers)->toHaveKey('sanitize');
        });

        it('includes SlothViewExtension directives', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);
            $provider->register();
            $provider->boot();

            $directives = $app['view']->getShared()['_directives'];
            expect($directives)->toHaveKey('wp_head');
            expect($directives)->toHaveKey('wp_footer');
            expect($directives)->toHaveKey('module');
            expect($directives)->toHaveKey('url');
        });

        it('calls share() on extensions and sets on view factory', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);
            $provider->register();
            $provider->boot();

            $shared = $app['view']->getShared();
            expect($shared)->toHaveKey('app');
        });
    });

    describe('normalizeEntries()', function (): void {
        it('converts plain string to name => callable', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);

            $reflection = new \ReflectionMethod($provider, 'normalizeEntries');
            $result = $reflection->invoke($provider, ['wp_head']);

            expect($result)->toBe(['wp_head' => 'wp_head']);
        });

        it('keeps key => string as-is', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);

            $reflection = new \ReflectionMethod($provider, 'normalizeEntries');
            $result = $reflection->invoke($provider, ['pll__' => 'pll__']);

            expect($result)->toBe(['pll__' => 'pll__']);
        });

        it('keeps key => closure as-is', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);
            $closure = fn($v) => $v;

            $reflection = new \ReflectionMethod($provider, 'normalizeEntries');
            $result = $reflection->invoke($provider, ['currency' => $closure]);

            expect($result['currency'])->toBe($closure);
        });
    });

    describe('conflict detection', function (): void {
        it('triggers E_USER_WARNING when a helper is registered twice in local env', function (): void {
            putenv('WP_ENV=local');

            $warnings = [];
            set_error_handler(function ($errno, $errstr) use (&$warnings): true {
                if ($errno === E_USER_WARNING) {
                    $warnings[] = $errstr;
                }
                return true;
            });

            // Two extensions both register 'debug'
            $app = makeTestApp();
            $provider = new class($app) extends \Sloth\View\ViewServiceProvider {
                protected array $adapters = []; // no adapters — skip boot

                protected function discoverExtensions(): array
                {
                    return [
                        \Sloth\Tests\Unit\View\ConflictExtensionA::class,
                        \Sloth\Tests\Unit\View\ConflictExtensionB::class,
                    ];
                }
            };

            $provider->register();
            $provider->boot();

            restore_error_handler();
            putenv('WP_ENV=');

            expect($warnings)->not()->toBeEmpty();
            expect($warnings[0])->toContain('conflict_helper');
        });
    });

    describe('discoverExtensions()', function (): void {
        it('SlothViewExtension comes first', function (): void {
            $app = makeTestApp();
            $provider = new ViewServiceProvider($app);

            $reflection = new \ReflectionMethod($provider, 'discoverExtensions');
            $extensions = $reflection->invoke($provider);

            expect($extensions[0])->toBe(\Sloth\View\Extensions\SlothViewExtension::class);
        });
    });
});
