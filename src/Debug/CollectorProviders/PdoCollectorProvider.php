<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DebugBarException;

class PdoCollectorProvider extends AbstractCollectorProvider
{

    /**
     * @inheritDoc
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $this->addCollector(new PDOCollector());
    }
}
