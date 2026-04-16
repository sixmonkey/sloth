<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\ACF;

use Sloth\ACF\ACFHelper;

/**
 * Unit tests for the ACFHelper class.
 */
describe('ACFHelper', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $helper = new ACFHelper();
            expect($helper)->toBeInstanceOf(ACFHelper::class);
        });
    });

    describe('addFilters()', function (): void {
        it('method exists', function (): void {
            $helper = new ACFHelper();
            expect(method_exists($helper, 'addFilters'))->toBeTrue();
        });
    });

    describe('load_image()', function (): void {
        it('method exists', function (): void {
            $helper = new ACFHelper();
            expect(method_exists($helper, 'load_image'))->toBeTrue();
        });
    });

    describe('autoSyncAcfFields()', function (): void {
        it('method exists', function (): void {
            $helper = new ACFHelper();
            expect(method_exists($helper, 'autoSyncAcfFields'))->toBeTrue();
        });
    });
});
