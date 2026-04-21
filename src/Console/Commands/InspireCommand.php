<?php

declare(strict_types=1);

namespace Sloth\Console\Commands;

use Sloth\Console\Command;

/**
 * Display a welcome message.
 *
 * @since 1.0.0
 */
class InspireCommand extends Command
{
    protected $signature = 'inspire';

    protected $description = 'Display a welcome message';

    public function handle(): int
    {
        \Termwind\render(<<<'HTML'
            <div class="py-1 ml-2">
                <div class="px-1 bg-red-300 text-black">🦥  Sloth</div>
                <em class="ml-1">
                  May the sloth be with you.
                </em>
            </div>
        HTML
        );

        return self::SUCCESS;
    }
}
