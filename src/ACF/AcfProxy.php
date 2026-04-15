<?php

namespace Sloth\ACF;

class AcfProxy
{
    public function __construct(private $fields) {}

    public function __call($name, $arguments)
    {
        return $this->fields[$name];
    }
}
