<?php

/**
 * PHP-CS-Fixer Configuration
 *
 * This configuration defines the code style rules for the Sloth package.
 * It uses the WordPress coding standard as a base with some modifications.
 *
 * @since 1.1.0
 */

$finder = PhpCsFixer\Finder::create()
    ->exclude([
        'vendor',
        'tests',
        'src/_view',
        'src/Assets',
        'build',
        '.git',
    ])
    ->in(__DIR__)
    ->name('*.php')
    ->notName('*.blade.php');

$config = new PhpCsFixer\Config();

return $config
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setCacheFile('.php-cs-fixer.cache')
    ->setRules([
        '@WordPress' => true,
        '@PHP82Migration' => true,

        // Additional rules for modern PHP
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => [
            'operators' => [
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'blank_line_after_namespace' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'case',
                'continue',
                'declare',
                'default',
                'do',
                'exit',
                'for',
                'foreach',
                'goto',
                'if',
                'include',
                'include_once',
                'require',
                'require_once',
                'return',
                'switch',
                'throw',
                'try',
                'while',
            ],
        ],
        'braces' => [
            'allow_single_line_anonymous_class_with_empty_body' => true,
            'allow_single_line_closure' => true,
        ],
        'cast_spaces' => ['space' => 'single'],
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
                'trait' => 'one',
            ],
        ],
        'class_definition' => [
            'single_line' => true,
            'single_item_single_line' => true,
        ],
        'combine_consecutive_unsets' => true,
        'concat_space' => ['spacing' => 'one'],
        'echo_tag_syntax' => true,
        'fully_qualified_strict_types' => true,
        'function_declaration' => [
            'closure_function_spacing' => 'one',
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'increment_style' => ['style' => 'post'],
        'indentation_type' => true,
        'lambda_not_used_import' => true,
        'linebreak_after_opening_tag' => true,
        'list_syntax' => ['syntax' => 'short'],
        'logical_operators_spaces' => true,
        'lowercase_cast' => true,
        'lowercase_constants' => true,
        'lowercase_keywords' => true,
        'lowercase_static_reference' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'method_chaining_indentation' => true,
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'new_line_for_chained_calls',
        ],
        'native_function_casing' => true,
        'native_type_declaration_casing' => true,
        'no_alias_functions' => true,
        'no_alternative_syntax' => true,
        'no_binary_operator' => false,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_blank_lines_before_namespace' => true,
        'no_break_comment' => true,
        'no_closing_tag' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'allow_comments' => true,
                'allow_single_line_anonymous_class_with_empty_body',
                'allow_single_line_closure',
                'extra',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
            ],
        ],
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_php4_constructor' => true,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_space_around_double_arrow' => true,
        'no_spaces_after_function_name' => true,
        'no_spaces_around_offset' => true,
        'no_superfluous_elseif' => true,
        'no_trailing_comma_in_list_call' => true,
        'no_trailing_comma_in_singleline_array' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_string' => true,
        'no_unneeded_control_parentheses' => true,
        'no_unneeded_curly_braces' => true,
        'no_unreachable_default_argument_value' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,
        'non_printable_character' => true,
        'normalize_index_brace' => true,
        'nullable_type_declaration' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'object_operator_without_whitespace' => true,
        'ordered_imports' => [
            'imports_order' => [
                'const',
                'function',
                'class',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'php_unit_fqcn_annotation' => true,
        'php_unit_method_casing' => ['case' => 'camel'],
        'phpdoc_add_missing_param_annotation' => [
            'only_untyped' => false,
        ],
        'phpdoc_align' => [
            'tags' => [
                'param',
                'return',
                'throws',
                'type',
                'var',
            ],
        ],
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_alias_tag' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_order' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_separation' => [
            'groups' => [
                ['deprecated', 'link', 'see', 'since'],
                ['author', 'copyright', 'license'],
                ['category', 'package', 'subpackage'],
                ['property', 'property-read', 'property-write'],
                ['param', 'return'],
                ['throws'],
            ],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => true,
        'phpdoc_to_comment' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => [
            'sort_algorithm' => 'alpha',
            'null_adjustment' => 'always_last',
        ],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,
        'php_unit_internal_class' => true,
        'php_unit_ordered_covers' => true,
        'phpdoc_no_service' => true,
        'phpdoc_param_order' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'protected_to_private' => true,
        'psr_autoloading' => true,
        'random_extension_migration' => true,
        'return_type_declaration' => true,
        'self_accessor' => true,
        'self_static_accessor' => true,
        'semicolon_after_instruction' => true,
        'set_type_to_cast' => true,
        'short_scalar_cast' => true,
        'simplified_null_return' => true,
        'single_blank_line_at_eof' => true,
        'single_class_element_per_statement' => [
            'elements' => [
                'const',
                'property',
            ],
        ],
        'single_import_per_statement' => true,
        'single_line_after_imports' => true,
        'single_line_comment_style' => true,
        'single_quote' => true,
        'space_after_semicolon' => [
            'remove_in_empty_for_expressions' => true,
        ],
        'standardize_not_equals' => true,
        'statement_indentation' => true,
        'strict_param' => true,
        'switch_case_semicolon_to_colon' => true,
        'switch_case_space' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_elvis_operator' => true,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => [
                'arrays',
                'arguments',
                'parameters',
            ],
        ],
        'trim_array_spaces' => true,
        'unary_operator_spaces' => true,
        'use_extension' => true,
        'visibility_required' => [
            'elements' => [
                'const',
                'method',
                'property',
            ],
        ],
        'void_return' => true,
        'whitespace_after_comma_in_array' => true,
        'word_wrap' => true,
    ]);
