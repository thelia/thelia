<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Rector\CodeQuality\Rector\FunctionLike\SimplifyUselessVariableRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Array_\RemoveDuplicatedArrayKeyRector;
use Rector\DeadCode\Rector\Assign\RemoveDoubleAssignRector;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\Block\ReplaceBlockToItsStmtsRector;
use Rector\DeadCode\Rector\BooleanAnd\RemoveAndTrueRector;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;
use Rector\DeadCode\Rector\ClassLike\RemoveTypedPropertyNonMockDocblockRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveArgumentFromDefaultParentCallRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveNullTagValueNodeRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessAssignFromPropertyPromotionRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnExprInConstructRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Concat\RemoveConcatAutocastRector;
use Rector\DeadCode\Rector\ConstFetch\RemovePhpVersionIdCheckRector;
use Rector\DeadCode\Rector\Expression\RemoveDeadStmtRector;
use Rector\DeadCode\Rector\Expression\SimplifyMirrorAssignRector;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\DeadCode\Rector\For_\RemoveDeadIfForeachForRector;
use Rector\DeadCode\Rector\For_\RemoveDeadLoopRector;
use Rector\DeadCode\Rector\Foreach_\RemoveUnusedForeachKeyRector;
use Rector\DeadCode\Rector\FuncCall\RemoveFilterVarOnExactTypeRector;
use Rector\DeadCode\Rector\FunctionLike\RemoveDeadReturnRector;
use Rector\DeadCode\Rector\If_\ReduceAlwaysFalseIfOrRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\If_\RemoveDeadInstanceOfRector;
use Rector\DeadCode\Rector\If_\RemoveTypedPropertyDeadInstanceOfRector;
use Rector\DeadCode\Rector\If_\RemoveUnusedNonEmptyArrayBeforeForeachRector;
use Rector\DeadCode\Rector\If_\SimplifyIfElseWithSameContentRector;
use Rector\DeadCode\Rector\If_\UnwrapFutureCompatibleIfPhpVersionRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Plus\RemoveDeadZeroAndOneOperationRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\DeadCode\Rector\Property\RemoveUselessReadOnlyTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\DeadCode\Rector\Return_\RemoveDeadConditionAboveReturnRector;
use Rector\DeadCode\Rector\Stmt\RemoveUnreachableStatementRector;
use Rector\DeadCode\Rector\Switch_\RemoveDuplicatedCaseInSwitchRector;
use Rector\DeadCode\Rector\Ternary\TernaryToBooleanOrFalseToBooleanAndRector;
use Rector\DeadCode\Rector\TryCatch\RemoveDeadCatchRector;
use Rector\DeadCode\Rector\TryCatch\RemoveDeadTryCatchRector;
use Rector\Php80\Rector\Property\NestedAnnotationToAttributeRector;
use Rector\Symfony\DependencyInjection\Rector\Class_\GetBySymfonyStringToConstructorInjectionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddTypeFromResourceDocblockRector;
use Rector\TypeDeclaration\Rector\Property\AddPropertyTypeDeclarationRector;

return RectorConfig::configure()
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/core/lib/Thelia/Core',
        ],
    )
    ->withSkip([
        'core/lib/Thelia/Form/Sale/SaleModificationForm.php',
    ])
    ->withPhpSets(
        php83: true,
    )
    ->withPreparedSets(
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        rectorPreset: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(
        symfony: true,
    )
    ->withComposerBased(
        symfony: true,
    )
    ->withRules([
        AddParamTypeDeclarationRector::class,
        AddReturnTypeDeclarationRector::class,
        AddPropertyTypeDeclarationRector::class,
        AddTypeFromResourceDocblockRector::class,

        NestedAnnotationToAttributeRector::class,
        GetBySymfonyStringToConstructorInjectionRector::class,

        // easy picks
        RemoveUnusedForeachKeyRector::class,
        RemoveDuplicatedArrayKeyRector::class,
        RecastingRemovalRector::class,
        RemoveAndTrueRector::class,
        SimplifyMirrorAssignRector::class,
        RemoveDeadContinueRector::class,
        RemoveUnusedNonEmptyArrayBeforeForeachRector::class,
        RemoveUselessReturnExprInConstructRector::class,
        ReplaceBlockToItsStmtsRector::class,
        RemoveFilterVarOnExactTypeRector::class,
        RemoveTypedPropertyDeadInstanceOfRector::class,
        TernaryToBooleanOrFalseToBooleanAndRector::class,
        RemoveDoubleAssignRector::class,
        RemoveUselessAssignFromPropertyPromotionRector::class,
        RemoveConcatAutocastRector::class,
        SimplifyIfElseWithSameContentRector::class,
        SimplifyUselessVariableRector::class,
        RemoveDeadZeroAndOneOperationRector::class,

        // docblock
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessReadOnlyTagRector::class,
        RemoveNonExistingVarAnnotationRector::class,
        RemoveUselessVarTagRector::class,

        // prioritize safe belt on RemoveUseless*TagRector that registered previously first
        RemoveNullTagValueNodeRector::class,
        RemovePhpVersionIdCheckRector::class,
        RemoveTypedPropertyNonMockDocblockRector::class,
        RemoveAlwaysTrueIfConditionRector::class,
        ReduceAlwaysFalseIfOrRector::class,
        RemoveUnusedPrivateClassConstantRector::class,
        RemoveUnusedPrivatePropertyRector::class,
        RemoveDuplicatedCaseInSwitchRector::class,
        RemoveDeadInstanceOfRector::class,
        RemoveDeadCatchRector::class,
        RemoveDeadTryCatchRector::class,
        RemoveDeadIfForeachForRector::class,
        RemoveDeadStmtRector::class,
        UnwrapFutureCompatibleIfPhpVersionRector::class,
        RemoveDeadConditionAboveReturnRector::class,
        RemoveDeadLoopRector::class,

        // removing methods could be risky if there is some magic loading them
        RemoveUnusedPrivateMethodParameterRector::class,
        RemoveUnusedPrivateMethodRector::class,
        RemoveUnreachableStatementRector::class,
        RemoveUnusedVariableAssignRector::class,

        // this could break framework magic autowiring in some cases
        RemoveEmptyClassMethodRector::class,
        RemoveDeadReturnRector::class,
        RemoveArgumentFromDefaultParentCallRector::class,
    ]);
