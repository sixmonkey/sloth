<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php84: true)
    ->withSets([
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ])
    ->withSkip([
        \Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
