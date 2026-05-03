<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DebugBarException;
use Sloth\Debug\Collectors\WordpressCollector;

class WordpressCollectorProvider extends AbstractCollectorProvider
{
    /**
     * @inheritDoc
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $this->addCollector(new WordpressCollector());
    }
}
