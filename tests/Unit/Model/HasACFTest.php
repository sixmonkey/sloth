<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Traits;

use Sloth\Model\Traits\HasACF;

/**
 * Minimal fixture model using HasACF.
 */
class ACFFixtureModel
{
    use HasACF;

    public function getAcfKey(): ?string
    {
        return '123';
    }
}

describe('HasACF', function (): void {

    describe('bootHasACF()', function (): void {
        it('does not throw when process_acf is false', function (): void {
            $app = makeTestApp();
            $app['config']->set('theme.process_acf', false);

            expect(fn () => ACFFixtureModel::bootHasACF())->not()->toThrow(\Throwable::class);
        });

        it('does not throw when process_acf is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('theme.process_acf', true);

            expect(fn () => ACFFixtureModel::bootHasACF())->not()->toThrow(\Throwable::class);
        });
    });

    describe('getFields()', function (): void {
        it('returns empty collection when ACF is not installed', function (): void {
            $model = new class {
                use HasACF;
                public function getAcfKey(): string { return '123'; }
            };

            // get_fields() doesn't exist in test environment
            $reflection = new \ReflectionMethod($model, 'getFields');
            $result = $reflection->invoke($model, $model);

            expect($result)->toBeEmpty();
        });
    });

    describe('getAcfAttribute()', function (): void {
        it('returns an AcfProxy instance', function (): void {
            $model = new class {
                use HasACF;
                public function getAcfKey(): string { return '123'; }
            };

            expect($model->getAcfAttribute())->toBeInstanceOf(\Sloth\ACF\AcfProxy::class);
        });
    });
});
