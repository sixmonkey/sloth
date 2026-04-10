<?php

declare(strict_types=1);

namespace Sloth\Field;

/**
 * Carbon Faker placeholder class.
 *
 * @since 1.0.0
 */
class CarbonFaker
{
    /**
     * Handle undefined method calls.
     *
     * @since 1.0.0
     *
     * @param string               $method Method name
     * @param array<int, mixed>   $args  Method arguments
     */
    public function __call(string $method, array $args = []): string
    {
        debug('got empty date!');

        return '';
    }
}
