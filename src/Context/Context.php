<?php

declare(strict_types=1);

namespace Sloth\Context;

use Sloth\Core\Application;
use Sloth\Model\User;

/**
 * Template context builder for Twig templates.
 *
 * Builds and provides the context array used when rendering Twig templates.
 * Includes WordPress site data, post/taxonomy/author context, and
 * Sloth-specific variables like current layout.
 *
 * @since 1.0.0
 * @see \Sloth\Context\ContextServiceProvider
 * @see \Sloth\Template\TemplateServiceProvider
 */
class Context
{
    /**
     * Template context.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $context = null;

    /**
     * Current model instance.
     *
     * @var mixed
     */
    protected mixed $currentModel = null;

    /**
     * Constructor for Context.
     *
     * @since 1.0.0
     */
    public function __construct(private Application $app) {}

    /**
     * Get the template context for Twig.
     *
     * @return array<string, mixed> Context array for Twig templates
     * @since 1.0.0
     *
     */
    public function getContext(): array
    {
        if (is_array($this->context)) {
            return $this->context;
        }

        $this->context = [
            'wp_title' => trim((string) wp_title('', false)),
            'site' => [
                'url' => (string) home_url(),
                'rdf' => (string) get_bloginfo('rdf_url'),
                'rss' => (string) get_bloginfo('rss_url'),
                'rss2' => (string) get_bloginfo('rss2_url'),
                'atom' => (string) get_bloginfo('atom_url'),
                'language' => get_bloginfo('language'),
                'charset' => get_bloginfo('charset'),
                'pingback' => (string) get_bloginfo('pingback_url'),
                'admin_email' => (string) get_bloginfo('admin_email'),
                'name' => (string) get_bloginfo('name'),
                'title' => (string) get_bloginfo('name'),
                'description' => (string) get_bloginfo('description'),
                'canonical_url' => (string) home_url((string) $_SERVER['REQUEST_URI']),
            ],
            'globals' => [
                'home_url' => (string) home_url('/'),
                'theme_url' => (string) get_template_directory_uri(),
                'images_url' => get_template_directory_uri() . '/assets/img',
            ],
            'sloth' => [
                'current_layout' => basename($this->currentLayout ?? '', '.twig'),
            ],
        ];

        $this->populatePostContext();
        $this->populateTaxonomyContext();
        $this->populateAuthorContext();

        $this->app->instance('sloth.context', $this->context);

        return $this->context;
    }

    /**
     * Populate post/page context.
     *
     * @since 1.0.0
     */
    protected function populatePostContext(): void
    {
        if (!is_single() && !is_page()) {
            return;
        }

        $qo = get_queried_object();

        if ($this->currentModel === null) {
            $models = $this->app['sloth.models'] ?? [];
            $modelClass = $models[$qo->post_type] ?? \Sloth\Model\Post::class;
            $a = call_user_func([$modelClass, 'find'], [$qo->ID]);
            $this->currentModel = $a->first();
        }

        $this->context['post'] = $this->currentModel;
        $this->context[$qo->post_type] = $this->currentModel;
    }

    /**
     * Populate taxonomy archive context.
     *
     * @since 1.0.0
     */
    protected function populateTaxonomyContext(): void
    {
        if (!is_tax()) {
            return;
        }

        global $taxonomy;
        if ($this->currentModel === null) {
            $taxonomies = $this->app['sloth.taxonomies'] ?? [];
            $taxonomyClass = $taxonomies[$taxonomy] ?? \Sloth\Model\Taxonomy::class;
            $a = call_user_func([$taxonomyClass, 'find'], [get_queried_object()->term_id]);
            $this->currentModel = $a->first();
        }

        $this->context['taxonomy'] = $this->currentModel;
        $this->context[$taxonomy] = $this->currentModel;
    }

    /**
     * Populate author archive context.
     *
     * @since 1.0.0
     */
    protected function populateAuthorContext(): void
    {
        if (!is_author()) {
            return;
        }

        if ($this->currentModel === null) {
            $this->currentModel = User::find(\get_queried_object()->id);
        }

        $this->context['user'] = $this->currentModel;
        $this->context['author'] = $this->currentModel;
    }
}
