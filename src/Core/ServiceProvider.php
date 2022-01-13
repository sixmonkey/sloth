<?php

namespace Sloth\Core;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

abstract class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Dynamically handle missing method calls.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method == 'boot') {
            return;
        }

        throw new \Exception("Call to undefined method [{$method}]");
    }
}
