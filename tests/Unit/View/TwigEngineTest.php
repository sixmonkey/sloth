<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\View;

use Sloth\View\Engines\Twig\TwigEngine;
use Sloth\View\ViewFinder;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Tests for Twig Engine.
 */
describe('Twig Engine', function (): void {

    describe('get()', function (): void {
        it('strips view root prefix from absolute path', function (): void {
            $viewPath = sys_get_temp_dir() . '/sloth_view_test_' . uniqid();
            mkdir($viewPath . '/Layout', 0755, true);

            $templateFile = $viewPath . '/Layout/single.twig';
            file_put_contents($templateFile, 'Hello {{ name }}');

            $loader = new FilesystemLoader([$viewPath, $viewPath . '/Layout']);
            $twig = new Environment($loader);

            $finder = new ViewFinder(new \Illuminate\Filesystem\Filesystem(), [$viewPath], []);
            $engine = new TwigEngine($twig, $finder);

            $result = $engine->get($templateFile, ['name' => 'World']);

            expect($result)->toBe('Hello World');

            unlink($templateFile);
            rmdir($viewPath . '/Layout');
            rmdir($viewPath);
        });

        it('renders with empty data', function (): void {
            $viewPath = sys_get_temp_dir() . '/sloth_view_test_' . uniqid();
            mkdir($viewPath, 0755, true);

            $templateFile = $viewPath . '/hello.twig';
            file_put_contents($templateFile, 'Hello World');

            $loader = new FilesystemLoader([$viewPath]);
            $twig = new Environment($loader);
            $finder = new ViewFinder(new \Illuminate\Filesystem\Filesystem(), [$viewPath], []);
            $engine = new TwigEngine($twig, $finder);

            $result = $engine->get($templateFile, []);

            expect($result)->toBe('Hello World');

            unlink($templateFile);
            rmdir($viewPath);
        });
    });
});
