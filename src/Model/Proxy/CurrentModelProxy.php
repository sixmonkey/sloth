<?php

namespace Sloth\Model\Proxy;

use Illuminate\Support\Str;

class CurrentModelProxy
{
    protected static array $instances = [];

    public static function __callStatic($method, $args)
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
