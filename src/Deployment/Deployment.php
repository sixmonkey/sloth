<?php

namespace Sloth\Deployment;

final class Deployment
{
    /**
     * Sloth\Deployment instance.
     *
     * @var \Sloth\Deployment\Deployment
     */
    protected static $instance = null;

    protected $hooks = [
        'edited_terms',
        'created_term',
        'post_updated',
        'acf/save_post',
    ];

    /**
     * Retrieve Sloth class instance.
     *
     * @return \Sloth\Deployment\Deployment
     */
    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * trigger the deployment
     */
    public function trigger()
    {
        if ($hook = getenv('SLOTH_DEPLOYMENT_WEBHOOK')) {
            wp_remote_post($hook);
        }
    }
}
