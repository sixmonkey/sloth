<?php

declare(strict_types=1);
namespace Sloth\View\Engines\Twig;

use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\ViewFinderInterface;
use Override;
use Twig\Environment;

/**
 * Twig template engine for illuminate/view.
 *
 * Registered via Factory::addExtension('twig', 'twig', ...) so that
 * illuminate/view automatically selects this engine for .twig files.
 *
 * Accepts both absolute paths (from ViewFinder) and relative paths
 * (from direct View::make() calls with dot-notation) — Twig's
 * FilesystemLoader handles the resolution for relative paths.
 *
 * @since 1.0.0
 */
class TwigEngine extends PhpEngine
{
    /**
     * @param Environment         $environment the Twig environment instance
     * @param ViewFinderInterface $finder      the illuminate view finder
     *
     * @since 1.0.0
     */
    public function __construct(
        protected readonly Environment $environment,
        protected readonly ViewFinderInterface $finder,
    ) {
    }

    /**
     * Get the evaluated contents of a Twig template.
     *
     * Accepts an absolute path from the ViewFinder and converts it to
     * a path relative to one of the registered view paths, which is
     * what Twig's FilesystemLoader expects.
     *
     * @param string               $path the absolute path to the template file
     * @param array<string, mixed> $data the template variables
     *
     * @since 1.0.0
     */
    #[Override]
    public function get($path, array $data = []): string
    {
        // Strip the view root prefix to get a path relative to the loader paths.
        // Twig's FilesystemLoader expects a relative path — not an absolute one.
        // Use realpath() on both sides to resolve symlinks (e.g. /var → /private/var on macOS).
        $realPath = realpath($path) ?: $path;

        foreach ($this->finder->getPaths() as $viewPath) {
            $realViewPath = realpath($viewPath);

            if ($realViewPath && str_starts_with($realPath, $realViewPath)) {
                $path = substr($realPath, strlen($realViewPath) + 1); // +1 to strip leading separator

                break;
            }
        }

        return $this->environment->render($path, $data);
    }
}
