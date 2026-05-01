<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DebugBarException;
use Symfony\Component\VarDumper\VarDumper;

class MessageCollectorProvider extends AbstractCollectorProvider
{
    /**
     * register and configure the MessagesCollector for debug bar in sloth
     *
     * @return void
     * @throws DebugBarException
     */
    public function boot(): void
    {
        $messageCollector = $this->debugBar->getMessagesCollector();
        $messageCollector->collectFileTrace(true);

        $originalHandler = VarDumper::setHandler(function ($var) use (&$originalHandler, $messageCollector): void {
            if ($originalHandler) {
                $originalHandler($var);
            }

            $messageCollector->addMessage($var);
        });

        $this->addCollector($messageCollector);
    }
}
