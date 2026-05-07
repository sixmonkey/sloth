<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Debug\CollectorsTest;

use Sloth\Debug\Collectors\SlothCollector;
use Sloth\Debug\Collectors\WordpressCollector;
use Sloth\Debug\Collectors\AcfCollector;
use Sloth\Debug\Collectors\QueryCollector;

/**
 * Unit tests for Debug collectors.
 */
describe('Collectors', function (): void {
    describe('SlothCollector', function (): void {
        it('can be instantiated', function (): void {
            $collector = new SlothCollector();

            expect($collector)->toBeInstanceOf(SlothCollector::class);
        });

        it('has correct name', function (): void {
            $collector = new SlothCollector();

            expect($collector->getName())->toBe('sloth');
        });

        it('returns array from collect', function (): void {
            $collector = new SlothCollector();

            $data = $collector->collect();

            expect($data)->toBeArray();
            expect($data)->toHaveKeys([
                'Environment',
                'Template-Hierarchy',
                'Models',
                'Taxonomies',
                'Loaded providers',
            ]);
        });

        it('has widgets', function (): void {
            $collector = new SlothCollector();

            $widgets = $collector->getWidgets();

            expect($widgets)->toHaveKey('sloth');
        });
    });

    describe('WordPressCollector', function (): void {
        it('can be instantiated', function (): void {
            $collector = new WordpressCollector();

            expect($collector)->toBeInstanceOf(WordpressCollector::class);
        });

        it('has correct name', function (): void {
            $collector = new WordpressCollector();

            expect($collector->getName())->toBe('wordpress');
        });

        it('returns array from collect', function (): void {
            $collector = new WordpressCollector();

            $result = $collector->collect();

            expect($result)->toBeArray();
            expect($result)->toHaveKey('Version');
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
            expect($data)->toHaveKey('groups');
            expect($data['groups'])->toBeArray();
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
            expect($data)->toHaveKeys([
                'nb_statements',
                'accumulated_duration',
                'accumulated_duration_str',
                'statements',
            ]);
        });

        it('queries have source property', function (): void {
            $collector = new QueryCollector();

            $data = $collector->collect();

            expect($data['statements'])->toBeArray();
        });
    });
});
