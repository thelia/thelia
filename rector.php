<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\DeadCode\Rector\Property\RemoveUselessReadOnlyTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php80\Rector\ClassMethod\AddParamBasedOnParentClassMethodRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\Property\AddPropertyTypeDeclarationRector;

return RectorConfig::configure()
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/core'
        ]
    )
    ->withSkip([
        'core/lib/Thelia/Form/Sale/SaleModificationForm.php'
    ])
    ->withPhpSets(
        php83: true
    )
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        rectorPreset: true,
        symfonyCodeQuality: true
    )
    ->withComposerBased(symfony: true)
    ->withRules([
        AddParamTypeDeclarationRector::class,
        AddReturnTypeDeclarationRector::class,
        AddPropertyTypeDeclarationRector::class,

        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessVarTagRector::class,
        RemoveUnusedPrivatePropertyRector::class,
        RemoveUselessReadOnlyTagRector::class,
        AddParamBasedOnParentClassMethodRector::class
    ]);

