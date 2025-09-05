<?php

use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withParallel(360, 8)
    ->withPaths([
        __DIR__ . '/spec',
        __DIR__ . '/src',
    ])
    ->withImportNames()
    ->withComposerBased(doctrine: true, symfony: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        SetList::ASSERT,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::NAMING,
        SetList::STRICT_BOOLEANS,
        SetList::TYPE_DECLARATION
    ]);

// CLI cmd: php vendor/bin/rector process --memory-limit 3G --no-diffs

