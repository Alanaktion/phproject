<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/cron',
        __DIR__ . '/tests',
    ])
    // ->withPhpSets(php81: true)
    ->withPhp74Sets()
    ->withTypeCoverageLevel(10)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withCodingStyleLevel(0)
    ->withSkip([
        // StringClassNameToClassConstantRector::class,
    ]);
