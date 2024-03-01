<?php

$header = 'Copyright (c) Adrian Jeledintan';

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor']);

return (new PhpCsFixer\Config())
    ->setRules(
        [
            '@PER-CS2.0' => true,
            '@PER-CS2.0:risky' => true,
            'header_comment' => ['header' => $header],
        ]
    )
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setFinder($finder);
