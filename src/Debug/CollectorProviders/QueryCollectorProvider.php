<?php
namespace Sloth\Debug\CollectorProviders;

use DebugBar\DebugBarException;
use Sloth\Debug\Collectors\QueryCollector;

class QueryCollectorProvider extends AbstractCollectorProvider
{
    /**
     * @inheritDoc
     *
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $this->addCollector(new QueryCollector());
    }
}
