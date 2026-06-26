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
     * @param array<string, mixed> $fields the ACF fields array
     *
     * @since 1.0.0
     */
    public function __construct(
        /** @var array<string, mixed> */
        private mixed $fields,
    ) {
    }

    /**
     * Magic method to get field values.
     *
     * @param  string            $name      the field name
     * @param  array<int, mixed> $arguments method arguments (unused)
     * @return mixed             the field value or null if not found
     *
     * @since 1.0.0
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->fields[$name] ?? null;
    }

    /**
     * Magic method to get field values.
     *
     * @param string $name the field name
     * @return mixed       the field value or null if not found
     *
     * @since 1.0.0
     */
    public function __get(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }
}
