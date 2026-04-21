<?php

declare(strict_types=1);

namespace Sloth\ACF;

/**
 * Proxy for accessing ACF field values.
 *
 * Provides dynamic access to ACF fields through magic methods.
 * Used by the HasACF trait to provide a fluent interface for
 * accessing ACF field values.
 *
 * @since 1.0.0
 */
class AcfProxy
{
    /**
     * Constructor for AcfProxy.
     *
     * @param array<string, mixed> $fields The ACF fields array
     * @since 1.0.0
     */
    public function __construct(private $fields) {}

    /**
     * Magic method to get field values.
     *
     * @param string $name The field name
     * @param array<string, mixed> $arguments Method arguments (unused)
     * @return mixed The field value or null if not found
     * @since 1.0.0
     */
    public function __call($name, $arguments)
    {
        return $this->fields[$name] ?? null;
    }
}
