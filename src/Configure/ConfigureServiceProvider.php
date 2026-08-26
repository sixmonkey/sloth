<?php

declare(strict_types=1);
namespace Sloth\Configure;

use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for loading theme configuration files.
 *
 * Scans the theme's config/ directory for *.php files and merges
 * them into the Laravel Config Repository. Each file's name (without
 * extension) becomes the config key — e.g. config/app.php merges into
 * the "app" key.
 *
 * Must run after framework providers (ApplicationServiceProvider,
 * ThemeServiceProvider) have merged their defaults, so theme files
 * can override them.
 *
 * @since 2.0.0
 */
class ConfigureServiceProvider extends ServiceProvider
{
    /**
     * Load and merge all theme config files.
     *
     * @since 2.0.0
     */
    #[Override]
    public function register(): void
    {
        $configDir = $this->app->configPath();

        if (!is_dir($configDir)) {
            return;
        }


        foreach (glob($configDir . '/*.php') as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $this->mergeConfigFrom($file, $key);
        }
    }
}
