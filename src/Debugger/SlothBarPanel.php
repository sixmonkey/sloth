<?php

declare(strict_types=1);

namespace Sloth\Debugger;

use Brain\Hierarchy\Hierarchy;
use Sloth\Facades\View;
use Tracy\IBarPanel;

/**
 * Tracy Debugger bar panel for Sloth.
 *
 * @since 1.0.0
 * @implements IBarPanel
 */
class SlothBarPanel implements IBarPanel
{
    /**
     * Get the panel HTML.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function getPanel(): string
    {
        $h = new Hierarchy();
        $currentTemplate = basename((string) $GLOBALS['sloth::plugin']->getCurrentTemplate(), '.twig');

        return View::make('Debugger.sloth-bar-panel')->with([
            'templates'       => $h->getTemplates(),
            'currentTemplate' => $currentTemplate,
        ])->render();
    }

    /**
     * Get the tab HTML.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function getTab(): string
    {
        $logo = file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logo.svg');

        return '<span title="SLOTH">' . $logo . '</span>';
    }
}
