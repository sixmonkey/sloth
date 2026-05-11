<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;
use Sloth\Model\User;

/**
 * Provides the current author to the Twig context.
 *
 * Available in templates as {{ author }} and {{ user }}.
 * Only active on author archive pages.
 *
 * @since 1.0.0
 */
class AuthorContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'author';
    }

    public function shouldResolve(): bool
    {
        return is_author();
    }

    public function resolve(): mixed
    {
        return User::find(get_queried_object()->ID);
    }
}
