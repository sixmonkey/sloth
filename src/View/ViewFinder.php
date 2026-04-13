<?php

declare(strict_types=1);

namespace Sloth\View;

use Illuminate\View\FileViewFinder;

/**
 * ViewFinder for locating view templates.
 *
 * @since 1.0.0
 * @extends FileViewFinder
 */
class ViewFinder extends FileViewFinder
{
    /**
     * Return a list of found views.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    #[\Override]
    public function getViews(): array
    {
        return $this->views;
    }
}
