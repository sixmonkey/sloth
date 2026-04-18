<?php

use Sloth\Plugin\Plugin;

$plugin = Plugin::getInstance();

$GLOBALS['sloth::plugin'] = new class ($plugin) {
    public function __construct(private $plugin) {}

    public function __get(string $key): mixed
    {
        trigger_error(
            '$GLOBALS[\'sloth::plugin\'] is deprecated. Use app() instead.',
            E_USER_DEPRECATED
        );

        return $this->plugin->$key;
    }

    public function __call(string $method, array $args): mixed
    {
        trigger_error(
            '$GLOBALS[\'sloth::plugin\']->' . $method . '() is deprecated. Use app() instead.',
            E_USER_DEPRECATED
        );

        return $this->plugin->$method(...$args);
    }
};
