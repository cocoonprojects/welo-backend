<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . "/module/*/src")
    ->in(__DIR__ . "/module/*/test")
;

return PhpCsFixer\Config::create()
    ->setRules(array(
        '@PSR2' => true,
    ))
    ->setFinder($finder)
;