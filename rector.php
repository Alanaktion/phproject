<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/cron',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/app/plugin',
    ])
    ->withPhpSets(php81: true)
    ->withPreparedSets(
        typeDeclarations: true,
        deadCode: true,
        codeQuality: true,
        earlyReturn: true,
        codingStyle: true,
        strictBooleans: true,
    )->withSkip([
        EncapsedStringsToSprintfRector::class,
    ]);
