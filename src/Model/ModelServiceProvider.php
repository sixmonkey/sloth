<?php

namespace Sloth\Model;

use Sloth\Core\ServiceProvider;
use Sloth\Model\Registrars\MenuRegistrar;
use Sloth\Model\Registrars\ModelRegistrar;
use Sloth\Model\Registrars\TaxonomyRegistrar;

class ModelServiceProvider extends ServiceProvider
{
    public function getHooks(): array
    {
        $menuRegistrar = new MenuRegistrar();
        $taxonomyRegistrar = new TaxonomyRegistrar();
        $modelRegistrar = new ModelRegistrar();

        return [
            'init' => [
                fn() => $menuRegistrar->init(),
                fn() => $taxonomyRegistrar->init(),
                fn() => $modelRegistrar->init(),
            ],
            'add_meta_boxes' => [
                fn() => $taxonomyRegistrar->addMetaBoxes(),
            ],
        ];
    }
}
