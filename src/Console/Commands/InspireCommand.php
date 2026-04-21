<?php

declare(strict_types=1);

namespace Sloth\Console\Commands;

use function Termwind\render;
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
        render(<<<'HTML'
            <div class="mt-2">
                <div class="flex gap-2">
                    <span class="text-green-500">
                         _
                        | |
                        | |__  __ _  _ __  _   _  _ __
                        | '_ \/ _` || '_ \| | | || '_ \
                        | | | | (_) | | | | | | || | | |
                        |_| |_|\__,_|_| |_||_| |_||_| |_|
                    </span>
                </div>
                <div class="mt-1 text-center text-gray-500">
                    Sloth WordPress Framework
                </div>
                <div class="mt-2 text-center">
                    <span class="bg-green-400 text-black px-2">
                        Hello World! 🚀
                    </span>
                </div>
            </div>
        HTML);

        return self::SUCCESS;
    }
}