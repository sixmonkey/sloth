<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Registrars;

use PHPUnit\Framework\TestCase;
use Sloth\Model\Registrars\MenuRegistrar;

/**
 * Unit tests for the MenuRegistrar class.
 */
describe('MenuRegistrar', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated with an app', function (): void {
            $app = $this->createMock(\Sloth\Core\Application::class);
            $registrar = new MenuRegistrar($app);
            expect($registrar)->toBeInstanceOf(MenuRegistrar::class);
        });
    });

    describe('init()', function (): void {
        it('method exists', function (): void {
            $app = $this->createMock(\Sloth\Core\Application::class);
            $registrar = new MenuRegistrar($app);
            expect(method_exists($registrar, 'init'))->toBeTrue();
        });
    });
});
