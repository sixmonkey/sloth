<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Debug\Collectors;

use Sloth\Debug\Collectors\SlothCollector;
use Sloth\Debug\Collectors\WordPressCollector;
use Sloth\Debug\Collectors\AcfCollector;
use Sloth\Debug\Collectors\QueryCollector;

/**
 * Unit tests for Debug collectors.
 */
describe('Collectors', function (): void {
    describe('SlothCollector', function (): void {
        it('can be instantiated with app', function (): void {
            $app = new \Illuminate\Container\Container();
            $collector = new SlothCollector($app);

            expect($collector)->toBeInstanceOf(SlothCollector::class);
        });

        it('has correct name', function (): void {
            $app = new \Illuminate\Container\Container();
            $collector = new SlothCollector($app);

            expect($collector->getName())->toBe('sloth');
        });

        it('returns array from collect', function (): void {
            $app = new \Illuminate\Container\Container();
            $collector = new SlothCollector($app);

            $data = $collector->collect();

            expect($data)->toBeArray();
            expect($data)->toHaveKeys(['providers', 'bindings', 'environment', 'models', 'taxonomies']);
        });

        it('has widgets', function (): void {
            $app = new \Illuminate\Container\Container();
            $collector = new SlothCollector($app);

            $widgets = $collector->getWidgets();

            expect($widgets)->toHaveKey('sloth');
        });
    });

    describe('WordPressCollector', function (): void {
        it('can be instantiated', function (): void {
            $collector = new WordPressCollector();

            expect($collector)->toBeInstanceOf(WordPressCollector::class);
        });

        it('has correct name', function (): void {
            $collector = new WordPressCollector();

            expect($collector->getName())->toBe('wordpress');
        });

        it('returns array from collect', function (): void {
            $collector = new WordPressCollector();

            $data = $collector->collect();

            expect($data)->toBeArray();
            expect($data)->toHaveKeys(['post_type', 'queried_object_id', 'template_slug', 'hooks', 'is_admin']);
        });
    });

    describe('AcfCollector', function (): void {
        it('can be instantiated', function (): void {
            $collector = new AcfCollector();

            expect($collector)->toBeInstanceOf(AcfCollector::class);
        });

        it('has correct name', function (): void {
            $collector = new AcfCollector();

            expect($collector->getName())->toBe('acf');
        });

        it('returns array from collect even without ACF', function (): void {
            $collector = new AcfCollector();

            $data = $collector->collect();

            expect($data)->toBeArray();
            expect($data)->toHaveKey('field_groups');
            expect($data['field_groups'])->toBeArray();
        });
    });

    describe('QueryCollector', function (): void {
        it('can be instantiated', function (): void {
            $collector = new QueryCollector();

            expect($collector)->toBeInstanceOf(QueryCollector::class);
        });

        it('has correct name', function (): void {
            $collector = new QueryCollector();

            expect($collector->getName())->toBe('queries');
        });

        it('returns array from collect', function (): void {
            $collector = new QueryCollector();

            $data = $collector->collect();

            expect($data)->toBeArray();
            expect($data)->toHaveKeys(['queries', 'count', 'total_time', 'slow']);
        });

        it('queries have source property', function (): void {
            $collector = new QueryCollector();

            $data = $collector->collect();

            expect($data['queries'])->toBeArray();
        });

        it('slow count is integer', function (): void {
            $collector = new QueryCollector();

            $data = $collector->collect();

            expect($data['slow'])->toBeInt();
        });
    });
});