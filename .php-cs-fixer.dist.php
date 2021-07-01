<?php

$header = 'Copyright (c) Adrian Jeledintan';

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor']);

return (new PhpCsFixer\Config())
    ->setRules(
        [
            '@PHP74Migration' => true,
            '@PHP74Migration:risky' => true,
            '@PSR2' => true,
            '@PhpCsFixer' => true,
            '@PhpCsFixer:risky' => true,
            '@Symfony' => true,
            '@Symfony:risky' => true,
            'header_comment' => ['header' => $header],
            'concat_space' => ['spacing' => 'one'],
            'native_constant_invocation' => ['strict' => false],
            'native_function_invocation' => ['strict' => false],
            'single_line_throw' => false,
            'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line']
        ]
    )
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setFinder($finder);
