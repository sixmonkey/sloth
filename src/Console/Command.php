<?php

declare(strict_types=1);

namespace Sloth\Console;

use Illuminate\Console\Command as BaseCommand;

/**
 * Base class for all Sloth console commands.
 *
 * Extend this in framework, app and theme commands.
 * Currently a thin wrapper — exists as an extension point
 * for future Sloth-specific console functionality.
 *
 * @since 1.0.0
 */
abstract class Command extends BaseCommand
{
}
