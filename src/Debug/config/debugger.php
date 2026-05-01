<?php

return [
    'editor' => 'phpstorm',
    'bar' => [
        'display' => app()->isLocal(),
        'collector_providers' => [
            \Sloth\Debug\CollectorProviders\MessageCollectorProvider::class,
            \Sloth\Debug\CollectorProviders\PdoCollectorProvider::class,
            \Sloth\Debug\CollectorProviders\SlothCollectorProvider::class,
            \Sloth\Debug\CollectorProviders\AcfCollectorProvider::class,
            \Sloth\Debug\CollectorProviders\WordpressCollectorProvider::class,
            \Sloth\Debug\CollectorProviders\QueryCollectorProvider::class,

            \Sloth\Debug\CollectorProviders\PhpInfoCollectorProvider::class,
            \Sloth\Debug\CollectorProviders\MemoryCollectorProvider::class,
        ],
        'dump_all' => true
    ],
    'json' => [
        'prepend' => true,
        'key' => '__debug'
    ]
];
