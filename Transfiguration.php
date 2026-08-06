<?php

declare(strict_types=1);

/**
 * Transfiguration — A lightweight PHP template engine.
 *
 * This is the main entry point. Require this file to autoload all
 * Transfiguration classes (when not using Composer autoload).
 */

// Guard against double-inclusion.
if (class_exists('Transfiguration\\Transfiguration', false)) {
    return;
}

require __DIR__ . '/Core/Hooks.php';
require __DIR__ . '/Core/Lexer.php';
require __DIR__ . '/Core/Parser.php';
require __DIR__ . '/Core/Translator.php';
require __DIR__ . '/Core/Transfiguration.php';
require __DIR__ . '/Helpers/Evaluator.php';
