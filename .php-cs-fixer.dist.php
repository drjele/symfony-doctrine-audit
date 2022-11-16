<?php

$header = 'Copyright (c) Adrian Jeledintan';

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor']);

return (new PhpCsFixer\Config())
    ->setRules(
        [
            '@Symfony' => true,
            '@Symfony:risky' => true,
            'header_comment' => ['header' => $header],
            'concat_space' => ['spacing' => 'one'],
            'global_namespace_import' => ['import_classes' => true],
            'native_constant_invocation' => ['include' => ['@all'], 'strict' => true],
            'native_function_invocation' => ['include' => ['@all'], 'strict' => true],
            'single_line_throw' => false,
            'cast_spaces' => ['space' => 'none'],
            'declare_strict_types' => true,
            'strict_comparison' => true,
            'strict_param' => true,
        ]
    )
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setFinder($finder);
