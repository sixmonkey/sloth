<?php

return [
    'editor' => 'vscode',
    'bar' => [
        'display' => app()->isLocal(),
        'collector_providers' => [
            \Sloth\Debug\CollectorProviders\MessageCollectorProvider::class
        ]
    ],
    'json' => [
        'prepend' => true,
        'key' => '__debug'
    ]
];
