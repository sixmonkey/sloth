<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use function Laravel\Prompts\select;
use function Laravel\Prompts\suggest;
use Illuminate\Support\Str;
use Sloth\Console\Command;

/**
 * Generate a new Layout Twig template.
 *
 * Without an argument, runs interactively and prompts for the template type.
 * With an argument, creates the template directly.
 *
 * ```bash
 * wp sloth make:layout                    # interactive
 * wp sloth make:layout single             # creates View/Layout/single.twig
 * wp sloth make:layout single-project     # creates View/Layout/single-project.twig
 * wp sloth make:layout page-contact       # creates View/Layout/page-contact.twig
 * ```
 *
 * @since 1.0.0
 */
class MakeLayoutCommand extends Command
{
    protected $signature = 'make:layout {name? : The template name (e.g. single, single-project, archive-event)}';

    protected $description = 'Create a new Layout Twig template';

    /**
     * WordPress template hierarchy — base templates and common variants.
     *
     * Keys are the template names, values are human-readable descriptions
     * shown in the interactive prompt.
     *
     * @var array<string, string>
     */
    private const array HIERARCHY = [
        'index'                      => 'Fallback for all pages',
        'front-page'                 => 'Static front page',
        'home'                       => 'Blog posts index',
        'single'                     => 'Single post',
        'single-{post_type}'         => 'Single post of a specific post type',
        'page'                       => 'Single page',
        'page-{slug}'                => 'Page with a specific slug',
        'archive'                    => 'Any archive',
        'archive-{post_type}'        => 'Archive for a specific post type',
        'category'                   => 'Category archive',
        'category-{slug}'            => 'Specific category archive',
        'tag'                        => 'Tag archive',
        'tag-{slug}'                 => 'Specific tag archive',
        'taxonomy'                   => 'Custom taxonomy archive',
        'taxonomy-{taxonomy}'        => 'Specific taxonomy archive',
        'taxonomy-{taxonomy}-{term}' => 'Specific taxonomy term archive',
        'author'                     => 'Author archive',
        'date'                       => 'Date archive',
        'search'                     => 'Search results',
        '404'                        => '404 not found',
        'attachment'                 => 'Attachment page',
        'singular'                   => 'Any single post or page',
        'embed'                      => 'Embed template',
    ];

    public function handle(): int
    {
        $name = $this->argument('name') ?? $this->askInteractive();

        if (!$name) {
            return self::FAILURE;
        }

        $path = app()->themePath("View/Layout/{$name}.twig");

        if (file_exists($path)) {
            $this->error("File already exists: View/Layout/{$name}.twig");

            return self::FAILURE;
        }

        $dir = dirname((string) $path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, $this->resolveStub($name));

        $this->info("Created: View/Layout/{$name}.twig");

        return self::SUCCESS;
    }

    /**
     * Run the interactive prompt to select a template type.
     */
    private function askInteractive(): string
    {
        // select() with key => label map returns the key directly — no parsing needed
        $template = select(
            label: 'Select a template type',
            options: self::HIERARCHY,
        );

        // Resolve {post_type} — suggest from registered models
        if (Str::contains($template, '{post_type}')) {
            $postType = suggest(
                label: 'Post type slug',
                options: app()->getAllModels()->keys()->all(),
                placeholder: 'e.g. project',
            );
            $template = str_replace('{post_type}', $postType, $template);
        }

        // Resolve {taxonomy} — suggest from registered taxonomies
        if (Str::contains($template, '{taxonomy}')) {
            $taxonomy = suggest(
                label: 'Taxonomy slug',
                options: collect(app()->getAllTaxonomies())->keys()->all(),
                placeholder: 'e.g. genre',
            );
            $template = str_replace('{taxonomy}', $taxonomy, $template);
        }

        // Resolve {term} — suggest from terms of the chosen taxonomy
        if (Str::contains($template, '{term}')) {
            $termOptions = function_exists('get_terms')
                ? collect(get_terms(['taxonomy' => $taxonomy ?? '', 'hide_empty' => false]))
                    ->pluck('slug')
                    ->all()
                : [];

            $term = suggest(
                label: 'Term slug',
                options: $termOptions,
                placeholder: 'e.g. featured',
            );
            $template = str_replace('{term}', $term, $template);
        }

        // Resolve {slug} — suggest from existing page slugs
        if (Str::contains($template, '{slug}')) {
            $pageOptions = function_exists('get_pages')
                ? collect(get_pages())
                    ->pluck('post_name')
                    ->all()
                : [];

            $slug = suggest(
                label: 'Page slug',
                options: $pageOptions,
                placeholder: 'e.g. contact',
            );
            $template = str_replace('{slug}', $slug, $template);
        }

        return $template;
    }

    /**
     * Resolve the stub content for the layout template.
     *
     * Uses a published custom stub if available, otherwise falls back
     * to the framework default.
     *
     * @param string $name
     */
    private function resolveStub(string $name): string
    {
        $custom = app()->appPath('stubs/Layout.twig.stub');

        if (file_exists($custom)) {
            return str_replace('{{ name }}', $name, file_get_contents($custom));
        }

        return file_get_contents(dirname(__DIR__, 4) . '/resources/stubs/Layout.twig.stub');
    }
}
