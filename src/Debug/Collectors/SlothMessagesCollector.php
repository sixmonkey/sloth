<?php

declare(strict_types=1);
namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\Message\MessageInterface;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataFormatter\JsonDataFormatter;

class SlothMessagesCollector extends MessagesCollector
{
    protected ?JsonDataFormatter $jsonFormatter = null;

    #[\Override]
    public function addMessage(mixed $message, string $label = 'info', array $context = []): void
    {
        if (is_string($message) && $context) {
            $message = $this->interpolate($message, $context);
        }

        $isString = is_string($message);
        $messageText = null;
        $messageHtml = null;
        $messageJson = null;

        if ($isString) {
            $messageText = $this->getDataFormatter()->formatVar($message);
        } else {
            if ($message instanceof MessageInterface) {
                $messageText = $message->getText();
                $messageHtml = $message->getHtml();
            } else {
                $messageHtml = $this->getDataFormatter()->formatVar($message);

                if ($this->compactDumps) {
                    $messageHtml = $this->compactMessageDump($messageHtml);
                }

                $messageJson = $this->getJsonFormatter()->formatVar($message);
            }
        }

        $contextJson = null;
        if ($context) {
            foreach ($context as $key => $value) {
                $formatted = $this->getDataFormatter()->formatVar($value);
                if ($this->isJsonVarDumperUsed()) {
                    $contextJson[$key] = $formatted;
                    $context[$key] = null;
                } else {
                    $context[$key] = $formatted;
                }
            }
        } else {
            $context = null;
        }

        $stackItem = [];
        if ($this->collectFile) {
            $stackItem = $this->getStackTraceItem(
                array_slice(
                    debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->backtraceLimit),
                    1,
                ),
            );
        }

        $this->messages[] = [
            'message' => $messageText,
            'message_html' => $messageHtml,
            'message_json' => $messageJson,
            'is_string' => $isString,
            'context' => $context,
            'context_json' => $contextJson,
            'label' => $label,
            'time' => microtime(true),
            'xdebug_link' => $stackItem ? $this->getXdebugLink($stackItem['file'], $stackItem['line'] ?? null) : null,
        ];

        if ($this->hasTimeDataCollector()) {
            $this->addTimeMeasure("[{$label}]: " . substr($isString ? $message : $this->getPlainTextFromVar($message), 0, 100), microtime(true));
        }
    }

    protected function getJsonFormatter(): JsonDataFormatter
    {
        if ($this->jsonFormatter === null) {
            $this->jsonFormatter = new JsonDataFormatter();
        }
        return $this->jsonFormatter;
    }
}
