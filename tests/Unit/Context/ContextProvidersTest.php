<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Context;

use Brain\Monkey\Functions;
use Sloth\Context\BlogInfo;
use Sloth\Context\Providers\AuthorContextProvider;
use Sloth\Context\Providers\GlobalsContextProvider;
use Sloth\Context\Providers\OptionsContextProvider;
use Sloth\Context\Providers\PostContextProvider;
use Sloth\Context\Providers\SiteContextProvider;
use Sloth\Context\Providers\SlothContextProvider;
use Sloth\Context\Providers\TaxonomyContextProvider;
use Sloth\Context\Providers\WpTitleContextProvider;

/**
 * Tests for built-in context providers.
 *
 * Note: resolve() methods that call WordPress functions (home_url, get_bloginfo
 * etc.) cannot be tested here because those functions are defined before
 * Patchwork loads and cannot be redefined. We test shouldResolve() and
 * the key() convention instead.
 */
describe('Built-in Context Providers', function (): void {

    describe('SiteContextProvider', function (): void {
        it('has correct key', function (): void {
            $blogInfo = new class extends BlogInfo {
                public function get(string $key): string { return ''; }
                public function homeUrl(string $path = ''): string { return ''; }
            };
            expect(new SiteContextProvider($blogInfo)->key())->toBe('site');
        });

        it('always resolves', function (): void {
            $blogInfo = new class extends BlogInfo {
                public function get(string $key): string { return ''; }
                public function homeUrl(string $path = ''): string { return ''; }
            };
            expect(new SiteContextProvider($blogInfo)->shouldResolve())->toBeTrue();
        });

        it('returns site data array with correct keys', function (): void {
            $blogInfo = new class extends BlogInfo {
                public function get(string $key): string
                {
                    return match($key) {
                        'name'        => 'My Site',
                        'description' => 'My Description',
                        'language'    => 'en-US',
                        'charset'     => 'UTF-8',
                        'admin_email' => 'admin@example.com',
                        default       => '',
                    };
                }

                public function homeUrl(string $path = ''): string
                {
                    return 'https://example.com' . $path;
                }
            };

            $result = new SiteContextProvider($blogInfo)->resolve();

            expect($result['name'])->toBe('My Site');
            expect($result['title'])->toBe('My Site');
            expect($result['description'])->toBe('My Description');
            expect($result['url'])->toBe('https://example.com');
            expect($result['language'])->toBe('en-US');
            expect($result['admin_email'])->toBe('admin@example.com');
        });
    });

    describe('GlobalsContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new GlobalsContextProvider()->key())->toBe('globals');
        });

        it('always resolves', function (): void {
            expect(new GlobalsContextProvider()->shouldResolve())->toBeTrue();
        });

        it('returns global URLs from app uris', function (): void {
            $app = makeTestApp();
            $app->instance('uri.home', 'https://example.com/');
            $app->instance('uri.theme', 'https://example.com/wp-content/themes/my-theme');
            \Sloth\Facades\Facade::setFacadeApplication($app);

            $result = new GlobalsContextProvider()->resolve();

            expect($result['home_url'])->toBe('https://example.com/');
            expect($result['theme_url'])->toBe('https://example.com/wp-content/themes/my-theme');
            expect($result['images_url'])->toEndWith('/assets/img');
        });
    });

    describe('WpTitleContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new WpTitleContextProvider()->key())->toBe('wp_title');
        });

        it('always resolves', function (): void {
            expect(new WpTitleContextProvider()->shouldResolve())->toBeTrue();
        });
    });

    describe('SlothContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new SlothContextProvider()->key())->toBe('sloth');
        });

        it('always resolves', function (): void {
            expect(new SlothContextProvider()->shouldResolve())->toBeTrue();
        });

        it('returns current_layout as basename without extension', function (): void {
            $result = new SlothContextProvider('Layout/single-project.twig')->resolve();

            expect($result['current_layout'])->toBe('single-project');
        });

        it('returns empty string when no layout is set', function (): void {
            $result = new SlothContextProvider()->resolve();

            expect($result['current_layout'])->toBe('');
        });
    });

    describe('PostContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new PostContextProvider()->key())->toBe('post');
        });

        it('resolves on single posts', function (): void {
            Functions\when('is_single')->justReturn(true);
            Functions\when('is_page')->justReturn(false);

            expect(new PostContextProvider()->shouldResolve())->toBeTrue();
        });

        it('resolves on pages', function (): void {
            Functions\when('is_single')->justReturn(false);
            Functions\when('is_page')->justReturn(true);

            expect(new PostContextProvider()->shouldResolve())->toBeTrue();
        });

        it('does not resolve on archives', function (): void {
            Functions\when('is_single')->justReturn(false);
            Functions\when('is_page')->justReturn(false);

            expect(new PostContextProvider()->shouldResolve())->toBeFalse();
        });
    });

    describe('TaxonomyContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new TaxonomyContextProvider()->key())->toBe('taxonomy');
        });

        it('resolves on taxonomy archives', function (): void {
            Functions\when('is_tax')->justReturn(true);

            expect(new TaxonomyContextProvider()->shouldResolve())->toBeTrue();
        });

        it('does not resolve on non-taxonomy pages', function (): void {
            Functions\when('is_tax')->justReturn(false);

            expect(new TaxonomyContextProvider()->shouldResolve())->toBeFalse();
        });
    });

    describe('AuthorContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new AuthorContextProvider()->key())->toBe('author');
        });

        it('resolves on author archives', function (): void {
            Functions\when('is_author')->justReturn(true);

            expect(new AuthorContextProvider()->shouldResolve())->toBeTrue();
        });

        it('does not resolve on non-author pages', function (): void {
            Functions\when('is_author')->justReturn(false);

            expect(new AuthorContextProvider()->shouldResolve())->toBeFalse();
        });
    });

    describe('OptionsContextProvider', function (): void {
        it('has correct key', function (): void {
            expect(new OptionsContextProvider()->key())->toBe('options');
        });

        it('always resolves', function (): void {
            expect(new OptionsContextProvider()->shouldResolve())->toBeTrue();
        });

        it('returns the options instance from the container', function (): void {
            $app = makeTestApp();
            $mockOptions = new \stdClass();
            $app->instance('options', $mockOptions);
            \Sloth\Facades\Facade::setFacadeApplication($app);

            expect(new OptionsContextProvider()->resolve())->toBe($mockOptions);
        });
    });
});
