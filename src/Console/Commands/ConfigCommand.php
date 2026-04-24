<?php

declare(strict_types=1);

namespace Sloth\Console\Commands;

use Sloth\Console\Command;

/**
 * Read configuration values.
 *
 * This command reads configuration values from either:
 * 1. Laravel's config repository (via config())
 * 2. Sloth's Configure store (via Configure::read())
 *
 * ## Usage
 *
 * ```bash
 * bin/sloth config:get app.name
 * bin/sloth config:get theme.colors.primary
 * ```
 *
 * ## Arguments
 *
 * | Argument | Description                    |
 * |----------|--------------------------------|
 * | key      | The config key to read (dot notation supported) |
 *
 * ## Exit Codes
 *
 * | Code | Description                   |
 * |------|-----------------------------|
 * | 0    | Config key found         |
 * | 1    | Config key not found   |
 *
 * @since 1.0.0
 * @see \Sloth\Configure\Configure
 */
class ConfigCommand extends Command
{
    /** @since 1.0.0 */
    protected $signature = 'config:get {key}';

    /** @since 1.0.0 */
    protected $description = 'Get a configuration value';

    /**
     * Execute the command.
     *
     * @since 1.0.0
     */
    public function handle(): int
    {
        $key = $this->argument('key');
        
        // Try Laravel config first
        $value = config($key);
        
        // Fall back to Configure
        if ($value === null) {
            $value = \Sloth\Configure\Configure::read($key);
        }
        
        if ($value !== null) {
            $this->line($this->laravel->make('files')->jsonify($value));
            return self::SUCCESS;
        }
        
        $this->warn("Config key '{$key}' not found.");
        return self::FAILURE;
    }
}