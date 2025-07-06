<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__.'/core',
        __DIR__.'/src',
        __DIR__.'/setup',
        __DIR__.'/public/install',
        __DIR__.'/tests'
    ])
    ->exclude([
        'var',
        'vendor',
        'node_modules',
        'cache',
        'log',
        'core/lib/Thelia/Model'
    ])
    ->notPath([
        'core/lib/Thelia/Model/Base/*',
        'core/lib/Thelia/Model/Map/*',
        'core/lib/Thelia/Model/om/*',
        'local/modules/*/Model/Base/*',
        'local/modules/*/Model/Map/*',
        'local/modules/*/Model/om/*',
    ])
    ->name('*.php')
    ->notName('*.tpl')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

(new Symfony\Component\Filesystem\Filesystem())->mkdir(__DIR__.'/var/cache-ci');

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__.'/var/cache-ci/.php_cs.cache')
    ->setRiskyAllowed(true)
    ->setRules([
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Modern PHP features
        'declare_strict_types' => true,
        'strict_comparison' => true,
        'strict_param' => true,
        'nullable_type_declaration' => true,
        'modernize_strpos' => true,
        'get_class_to_class_keyword' => true,


        // Array notation
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'normalize_index_brace' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],
        'whitespace_after_comma_in_array' => true,

        // Attributes PHP 8+
        'attribute_empty_parentheses' => [
            'use_parentheses' => false,
        ],
        'spaces_inside_parentheses' => [
            'space' => 'none',
        ],

        // Binary operators
        'binary_operator_spaces' => [
            'default' => 'single_space'
        ],
        'concat_space' => ['spacing' => 'one'],
        'operator_linebreak' => ['only_booleans' => true],
        'logical_operators' => true,
        'new_with_braces' => true,
        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => false,
        'object_operator_without_whitespace' => true,
        'standardize_increment' => true,
        'standardize_not_equals' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_null_coalescing' => true,
        'unary_operator_spaces' => true,

        // Blank lines
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'switch', 'for', 'foreach', 'while', 'do'],
        ],
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
                'use_trait',
            ],
        ],

        // Casts
        'cast_spaces' => ['space' => 'single'],
        'no_short_bool_cast' => true,
        'no_unset_cast' => true,
        'modernize_types_casting' => true,
        'set_type_to_cast' => true,

        // Class notation with attributes support
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'none',
                'property' => 'none',
                'method' => 'one',
                'trait_import' => 'none',
                'case' => 'none',
            ],
        ],
        'class_definition' => [
            'single_line' => true,
            'single_item_single_line' => true,
            'multi_line_extends_each_single_line' => true,
            'space_before_parenthesis' => true,
        ],
        'protected_to_private' => true,
        'self_static_accessor' => true,
        'single_class_element_per_statement' => true,
        'visibility_required' => true,

        // Comments
        'comment_to_phpdoc' => true,
        'multiline_comment_opening_closing' => true,
        'no_empty_comment' => true,
        'single_line_comment_style' => ['comment_types' => ['hash']],

        // Control structures
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'elseif' => true,
        'empty_loop_body' => true,
        'include' => true,
        'no_alternative_syntax' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_curly_braces' => true,
        'no_useless_else' => true,
        'simplified_if_return' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'yoda_style' => true,

        // Function notation
        'function_declaration' => true,
        'function_typehint_space' => true,
        'lambda_not_used_import' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'no_spaces_after_function_name' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'return_type_declaration' => true,
        'static_lambda' => true,

        // Imports
        'fully_qualified_strict_types' => true,
        'no_leading_import_slash' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'single_line_after_imports' => true,

        // Language constructs
        'declare_equal_normalize' => true,
        'dir_constant' => true,
        'explicit_indirect_variable' => true,
        'function_to_constant' => true,
        'is_null' => true,
        'no_alias_functions' => true,
        'no_alias_language_construct_call' => true,
        'pow_to_exponentiation' => true,
        'random_api_migration' => true,

        // Naming
        'no_homoglyph_names' => true,

        // Namespace
        'clean_namespace' => true,
        'no_leading_namespace_whitespace' => true,

        // PHP tag
        'echo_tag_syntax' => true,
        'full_opening_tag' => true,
        'linebreak_after_opening_tag' => true,
        'no_closing_tag' => true,

        // PHPDoc
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'allow_unused_params' => false,
            'remove_inheritdoc' => true,
            'allow_hidden_params' => true,
        ],
        'phpdoc_no_empty_return' => true,
        'phpdoc_to_comment' => [
            'ignored_tags' => ['todo', 'fixme', 'deprecated', 'see'],
        ],
        'phpdoc_to_property_type' => true,
        'phpdoc_to_param_type' => true,
        'phpdoc_to_return_type' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'general_phpdoc_annotation_remove' => [
            'annotations' => [
                'package',
                'subpackage',
                'version',
                'api',
                'since'
            ],
        ],
        'phpdoc_line_span' => [
            'const' => 'single',
            'property' => 'single',
            'method' => 'multi',
        ],
        'phpdoc_tag_casing' => [
            'tags' => [
                'inheritDoc' => 'inheritdoc',
            ],
        ],

        // Return notation
        'no_useless_return' => true,
        'return_assignment' => true,
        'simplified_null_return' => true,

        // Semicolon
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'no_empty_statement' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'semicolon_after_instruction' => true,
        'space_after_semicolon' => true,

        // String notation
        'escape_implicit_backslashes' => true,
        'explicit_string_variable' => true,
        'heredoc_to_nowdoc' => true,
        'no_binary_string' => true,
        'simple_to_complex_string_variable' => true,
        'single_quote' => true,

        // Whitespace
        'compact_nullable_typehint' => true,
        'heredoc_indentation' => true,
        'indentation_type' => true,
        'line_ending' => true,
        'method_chaining_indentation' => true,
        'no_spaces_around_offset' => true,
        'no_spaces_inside_parenthesis' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_whitespace_in_blank_line' => true,
        'types_spaces' => true,

        // Performance optimizations
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'native_constant_invocation' => [
            'fix_built_in' => false,
            'include' => ['DIRECTORY_SEPARATOR', 'PHP_SAPI', 'PHP_VERSION_ID'],
            'scope' => 'namespaced',
            'strict' => true,
        ],

        'single_line_throw' => true,
        'header_comment' => [
            'header' => implode("\n", [
                'This file is part of the Thelia package.',
                'http://www.thelia.net',
                '',
                '(c) OpenStudio <info@thelia.net>',
                '',
                'For the full copyright and license information, please view the LICENSE',
                'file that was distributed with this source code.',
            ]),
        ],
    ])
    ->setFinder($finder)
    ;
