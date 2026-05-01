<?php

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DebugBarException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\VarDumper\VarDumper;

class MessageCollectorProvider extends AbstractCollectorProvider
{
    /**
     * register and configure the MessagesCollector for debug bar in sloth
     *
     * @return void
     * @throws DebugBarException|BindingResolutionException
     */
    public function boot(): void
    {
        $messageCollector = $this->debugBar->getMessagesCollector();
        $messageCollector->setTimeDataCollector($this->debugBar->getTimeCollector());

        $messageCollector->collectFileTrace(true);
        $messageCollector->addBacktraceExcludePaths([
            '/src/',
        ]);
        $messageCollector->setEditorLinkTemplate(config('debugger.editor', 'phpstorm'));

        $originalHandler = VarDumper::setHandler(function ($var) use (&$originalHandler, $messageCollector): void {
            if ($originalHandler && !config('debugger.bar.dump_all', false)) {
                $originalHandler($var);
            }
            $messageCollector->addMessage($var);
        });

        $this->addCollector($messageCollector);
    }
}
