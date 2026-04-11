<?php

declare(strict_types=1);

namespace Sloth\Deployment;

use Sloth\Singleton\Singleton;

/**
 * Deployment class for triggering deployment webhooks.
 *
 * @since 1.0.0
 */
class Deployment extends Singleton
{
    /**
     * WordPress hooks to trigger deployment.
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected array $hooks = [
        'edited_terms',
        'created_term',
        'post_updated',
        'acf/save_post',
    ];

    /**
     * Boot the deployment hooks.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if ((bool) getenv('SLOTH_DEPLOYMENT_WEBHOOK')) {
            foreach ($this->hooks as $hook) {
                add_action($hook, $this->trigger(...));
            }
        }
    }

    /**
     * Trigger the deployment webhook.
     *
     * @since 1.0.0
     */
    public function trigger(): void
    {
        $hook = getenv('SLOTH_DEPLOYMENT_WEBHOOK');
        if ($hook) {
            wp_remote_post($hook);
        }
    }
}
