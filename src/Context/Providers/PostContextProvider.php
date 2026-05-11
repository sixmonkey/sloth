<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides the current post to the Twig context.
 *
 * Available in templates as {{ post }} and {{ {post_type} }} (e.g. {{ project }}).
 * Only active on single post and page views.
 *
 * @since 1.0.0
 */
class PostContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'post';
    }

    public function shouldResolve(): bool
    {
        return is_single() || is_page();
    }

    public function resolve(): mixed
    {
        $qo = get_queried_object();
        $models = app('sloth.models') ?? [];
        $modelClass = $models[$qo->post_type] ?? \Sloth\Model\Post::class;

        return call_user_func([$modelClass, 'find'], [$qo->ID])->first();
    }
}
