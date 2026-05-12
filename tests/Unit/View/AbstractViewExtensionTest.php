<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\View;

use Sloth\View\Extensions\AbstractViewExtension;

/**
 * Fixture extension with no overrides.
 */
class EmptyExtension extends AbstractViewExtension {}

/**
 * Fixture extension with all methods overridden.
 */
class FullExtension extends AbstractViewExtension
{
    #[\Override]
    public function getHelpers(): array
    {
        return ['currency' => fn($v): string => $v . ' €'];
    }

    #[\Override]
    public function getDirectives(): array
    {
        return ['wp_head' => 'wp_head'];
    }

    #[\Override]
    public function share(): array
    {
        return ['my_var' => 'value'];
    }
}

describe('AbstractViewExtension', function (): void {
    describe('defaults', function (): void {
        it('getHelpers() returns empty array by default', function (): void {
            expect(new EmptyExtension()->getHelpers())->toBe([]);
        });

        it('getDirectives() returns empty array by default', function (): void {
            expect(new EmptyExtension()->getDirectives())->toBe([]);
        });

        it('share() returns empty array by default', function (): void {
            expect(new EmptyExtension()->share())->toBe([]);
        });
    });

    describe('subclass', function (): void {
        it('getHelpers() returns defined helpers', function (): void {
            $ext = new FullExtension();
            expect($ext->getHelpers())->toHaveKey('currency');
        });

        it('getDirectives() returns defined directives', function (): void {
            $ext = new FullExtension();
            expect($ext->getDirectives())->toHaveKey('wp_head');
        });

        it('share() returns defined shared variables', function (): void {
            $ext = new FullExtension();
            expect($ext->share())->toBe(['my_var' => 'value']);
        });
    });
});
