<?php

declare(strict_types=1);

use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\AssignmentInConditionSniff;
use PhpCsFixer\Fixer\CastNotation\ModernizeTypesCastingFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfAccessorFixer;
use PhpCsFixer\Fixer\ConstantNotation\NativeConstantInvocationFixer;
use PhpCsFixer\Fixer\FunctionNotation\FopenFlagsFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\FunctionNotation\NullableTypeDeclarationForDefaultNullValueFixer;
use PhpCsFixer\Fixer\FunctionNotation\SingleLineThrowFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\ExplicitIndirectVariableFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\OperatorLinebreakFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocIndentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoPackageFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocToCommentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimConsecutiveBlankLineSeparationFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpTag\LinebreakAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitConstructFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitDedicateAssertInternalTypeFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMockFixer;
use PhpCsFixer\Fixer\PhpUnit\PhpUnitMockShortWillReturnFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\Whitespace\CompactNullableTypehintFixer;
use PhpCsFixer\Fixer\Whitespace\NoWhitespaceInBlankLineFixer;
use Symplify\CodingStandard\Fixer\Spacing\StandaloneLineConstructorParamFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths(
        [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ]
    );

    $ecsConfig->dynamicSets([
        '@Symfony',
        '@Symfony:risky',
    ]);

    $ecsConfig->sets([
        SetList::ARRAY,
        SetList::CONTROL_STRUCTURES,
        SetList::DOCBLOCK,
        SetList::STRICT,
        SetList::PSR_12,
    ]);

    $ecsConfig->rules([
        ModernizeTypesCastingFixer::class,
        FopenFlagsFixer::class,
        NativeConstantInvocationFixer::class,
        NullableTypeDeclarationForDefaultNullValueFixer::class,
        VoidReturnFixer::class,
        OperatorLinebreakFixer::class,
        PhpdocLineSpanFixer::class,
        PhpdocOrderFixer::class,
        PhpUnitConstructFixer::class,
        PhpUnitDedicateAssertInternalTypeFixer::class,
        PhpUnitMockFixer::class,
        PhpUnitMockShortWillReturnFixer::class,
        NoUselessReturnFixer::class,
        NoUnusedImportsFixer::class,
        NoWhitespaceInBlankLineFixer::class,
        BlankLineBeforeStatementFixer::class,
        CompactNullableTypehintFixer::class,
    ]);

    $ecsConfig->ruleWithConfiguration(PhpdocOrderFixer::class, ['order' => ['param', 'throws', 'return']]);
    $ecsConfig->ruleWithConfiguration(
        ClassAttributesSeparationFixer::class,
        ['elements' => ['property' => 'one', 'method' => 'one']]
    );
    $ecsConfig->ruleWithConfiguration(MethodArgumentSpaceFixer::class, ['on_multiline' => 'ensure_fully_multiline']);
    $ecsConfig->ruleWithConfiguration(ConcatSpaceFixer::class, ['spacing' => 'one']);
    $ecsConfig->ruleWithConfiguration(
        GeneralPhpdocAnnotationRemoveFixer::class,
        ['annotations' => ['copyright', 'category']]
    );
    $ecsConfig->ruleWithConfiguration(
        NoSuperfluousPhpdocTagsFixer::class,
        ['allow_unused_params' => true, 'allow_mixed' => true]
    );
    $ecsConfig->ruleWithConfiguration(PhpUnitDedicateAssertFixer::class, ['target' => 'newest']);
    $ecsConfig->ruleWithConfiguration(SingleQuoteFixer::class, ['strings_containing_single_quote_chars' => true]);
    // workaround for https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/5495
    $ecsConfig->ruleWithConfiguration(BinaryOperatorSpacesFixer::class, [
        'operators' => [
            '|' => null,
            '&' => null,
        ],
    ]);

    $ecsConfig->parallel();

    $ecsConfig->skip([
        SingleLineThrowFixer::class => null,
        SelfAccessorFixer::class => null,
        ExplicitIndirectVariableFixer::class => null,
        BlankLineAfterOpeningTagFixer::class => null,
        PhpdocSummaryFixer::class => null,
        ExplicitStringVariableFixer::class => null,
        AssignmentInConditionSniff::class => null,
        PhpdocToCommentFixer::class => null,
        PhpdocAlignFixer::class => null,
        // skip php files in node modules (stylelint ships both js and php)
        '**/node_modules',
        // would otherwise destroy markdown in the description of a route annotation, since markdown interpreted spaces/indents
        PhpdocIndentFixer::class => [
            'src/**/*Controller.php',
            'src/**/*Route.php',
        ],
        // would otherwise remove lines in the description of route annotations
        PhpdocTrimConsecutiveBlankLineSeparationFixer::class => [
            'src/**/*Controller.php',
            'src/**/*Route.php',
        ],
        PhpdocNoPackageFixer::class => null,
        StandaloneLineConstructorParamFixer::class => null,
        LinebreakAfterOpeningTagFixer::class => null,
        FopenFlagsFixer::class => null,
    ]);
};
