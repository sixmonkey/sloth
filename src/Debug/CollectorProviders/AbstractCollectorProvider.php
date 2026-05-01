<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DebugBar;
use DebugBar\DebugBarException;

abstract class AbstractCollectorProvider
{
    /**
     * Constructor for a CollectorProvider
     *
     * Collector providers are used to configure and add DataCollectors to the debug-bar
     *
     * @param DebugBar $debugBar
     * @see https://php-debugbar.com/collectors/base/
     */
    public function __construct(protected DebugBar $debugBar)
    {
    }

    /**
     * Adds a collector to the debug bar
     *
     * @throws DebugBarException
     */
    protected function addCollector(DataCollectorInterface $collector): void
    {
        $this->debugBar->addCollector($collector);
    }

    /**
     * Check if a collector exists already in the bar
     *
     * @param string $name
     * @return bool
     */
    public function hasCollector(string $name): bool
    {
        return $this->debugBar->hasCollector($name);
    }

    /**
     * Getter for a certain collector
     *
     * @throws DebugBarException
     */
    public function getCollector(string $name): DataCollectorInterface
    {
        return $this->debugBar->getCollector($name);
    }

    /**
     * Method used to boot the DataCollector in question
     *
     * @return void
     */
    abstract public function boot(): void;
}
