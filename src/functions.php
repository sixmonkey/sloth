<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Sloth\Facades\Facade;
use Tracy\Debugger;

if (!function_exists('debug')) {
    /**
     * Dumps variables to Tracy bar for debugging.
     *
     * @param mixed ...$vars Variables to dump
     *
     * @return mixed Returns the first variable unchanged
     */
    function debug(mixed ...$vars): mixed
    {
        if (class_exists(Debugger::class)) {
            foreach ($vars as $var) {
                Debugger::barDump($var);
            }
        }

        return $vars[0] ?? null;
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return mixed
     */
    function config($key = null, $default = null): mixed
    {
        $app = Facade::getFacadeApplication();
        if ($app !== null && $app->bound('config')) {
            /** @var Repository $repository */
            $repository = $app->make('config');
            return $repository->get($key, $default);
        }
        return $default;
    }
}
