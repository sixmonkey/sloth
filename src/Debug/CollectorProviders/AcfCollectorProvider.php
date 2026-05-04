<?php

namespace Sloth\Debug\CollectorProviders;

use Sloth\Debug\CollectorProviders\AbstractCollectorProvider;
use Sloth\Debug\Collectors\AcfCollector;

class AcfCollectorProvider extends AbstractCollectorProvider
{
    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->addCollector(new AcfCollector());
    }
}
