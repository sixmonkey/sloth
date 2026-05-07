<?php
namespace Sloth\Debug\CollectorProviders;

use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DebugBarException;

class PhpInfoCollectorProvider extends AbstractCollectorProvider
{
    /**
     * @inheritDoc
     *
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $this->addCollector(new PhpInfoCollector());
    }
}
