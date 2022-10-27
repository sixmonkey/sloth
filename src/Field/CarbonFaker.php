<?php


namespace Sloth\Field;


class CarbonFaker {
    public function __call( $method, $args = [] ) {
        debug( 'got empty date!' );

        return '';
    }
}
