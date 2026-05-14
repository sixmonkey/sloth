<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Admin;

use Sloth\Admin\AdminServiceProvider;

describe('AdminServiceProvider', function (): void {

    describe('register()', function (): void {
        it('binds customizer in the container', function (): void {
            $app = makeTestApp();
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($app->bound('customizer'))->toBeTrue();
        });

        it('has correct default config values', function (): void {
            $app = makeTestApp();

            expect(config('admin.footer'))->toBeTrue();
            expect(config('admin.cleanup_menu'))->toBeTrue();
            expect(config('admin.hide_updates.core'))->toBeFalse();
            expect(config('admin.hide_updates.plugins'))->toBeFalse();
            expect(config('admin.hide_updates.themes'))->toBeFalse();
        });
    });

    describe('getFilters()', function (): void {
        it('registers footer filter when admin.footer is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.footer', true);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getFilters())->toHaveKey('update_footer');
        });

        it('does not register footer filter when admin.footer is false', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.footer', false);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getFilters())->not()->toHaveKey('update_footer');
        });

        it('registers core update filter when hide_updates.core is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.footer', false);
            $app['config']->set('admin.hide_updates.core', true);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getFilters())->toHaveKey('pre_site_transient_update_core');
        });

        it('registers plugins update filter when hide_updates.plugins is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.footer', false);
            $app['config']->set('admin.hide_updates.plugins', true);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getFilters())->toHaveKey('pre_site_transient_update_plugins');
        });

        it('registers themes update filter when hide_updates.themes is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.footer', false);
            $app['config']->set('admin.hide_updates.themes', true);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getFilters())->toHaveKey('pre_site_transient_update_themes');
        });

        it('returns no update filters when all hide_updates are false', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.footer', false);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            $filters = $provider->getFilters();
            expect($filters)->not()->toHaveKey('pre_site_transient_update_core');
            expect($filters)->not()->toHaveKey('pre_site_transient_update_plugins');
            expect($filters)->not()->toHaveKey('pre_site_transient_update_themes');
        });
    });

    describe('getHooks()', function (): void {
        it('registers admin_menu hook when cleanup_menu is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.cleanup_menu', true);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getHooks())->toHaveKey('admin_menu');
        });

        it('does not register admin_menu hook when cleanup_menu is false', function (): void {
            $app = makeTestApp();
            $app['config']->set('admin.cleanup_menu', false);
            $provider = new AdminServiceProvider($app);
            $provider->register();

            expect($provider->getHooks())->not()->toHaveKey('admin_menu');
        });
    });
});
