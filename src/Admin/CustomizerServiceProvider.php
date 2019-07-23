<?php

namespace Sloth\Admin;

use Sloth\Core\ServiceProvider;

class CustomizerServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton( 'customizer',
            function ( $container ) {
                return Customizer::getInstance();
            } );
    }
}
