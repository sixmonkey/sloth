<?php
namespace Sloth\Debug\CollectorProviders;

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
