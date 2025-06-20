<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/core'
        ]
    )
    ->withPhpSets(
        php54: true
    );
