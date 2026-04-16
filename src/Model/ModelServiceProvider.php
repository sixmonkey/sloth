<?php

namespace Sloth\Model;

use Sloth\Core\ServiceProvider;
use Sloth\Model\Registrars\ModelRegistrar;

class ModelServiceProvider extends ServiceProvider
{
    public function getHooks(): array
    {
        return [
            'init' => [
                fn() => new ModelRegistrar(),
            ],
        ];
    }
}
