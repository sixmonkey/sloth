<?php

declare(strict_types=1);

namespace Sloth\Model\Proxy;

use Illuminate\Support\Str;

/**
 * Proxy for calling model methods on the current post.
 *
 * Provides static magic methods to call model instance methods for the
 * current WordPress post. Methods ending in "Echo" output the result.
 *
 * Used by extended-cpts admin columns to call model methods from
 * callable arrays that are safe for var_export.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 */
class CurrentModelProxy
{
    /**
     * Cached model instances by post ID.
     *
     * @var array<int, \Sloth\Model\Model>
     * @since 1.0.0
     */
    protected static array $instances = [];

    /**
     * Call a model method on the current post.
     *
     * @param string $method The method name to call.
     * @param array<int, mixed> $args Method arguments.
     * @return mixed The method result, or null if no model found.
     * @since 1.0.0
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $echo = false;
        if (Str::endsWith($method, 'Echo')) {
            $method = Str::beforeLast($method, 'Echo');
            $echo = true;
        }
        $id = get_the_ID();
        $postType = get_post_type($id);
        $postTypes = app()->get('sloth.models');

        if (!isset($postTypes[$postType])) {
            return null;
        }

        $model = self::$instances[$id] ?? self::$instances[$id] = $postTypes[$postType]::find($id);

        $result = $model->$method($args);

        if ($echo) {
            echo $result;
        }

        return $result;
    }
}
