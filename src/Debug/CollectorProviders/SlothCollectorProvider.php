<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DebugBarException;
use Sloth\Debug\Collectors\SlothCollector;

class SlothCollectorProvider extends AbstractCollectorProvider
{

    /**
     * @inheritDoc
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $this->addCollector(new SlothCollector());
    }
}
