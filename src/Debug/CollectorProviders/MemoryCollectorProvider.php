<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DebugBarException;

class MemoryCollectorProvider extends AbstractCollectorProvider
{

    /**
     * @inheritDoc
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $this->addCollector(new MemoryCollector());
    }
}
