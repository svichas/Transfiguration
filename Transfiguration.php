<?php 

// check if is already defined
if (class_exists("Transfiguration")) {
	return;
}

/*
Require all files for transfiguration
*/
require 'Core/Hooks.php';
require 'Core/Lexer.php';
require 'Core/Parser.php';
require 'Core/Translator.php';
require 'Core/Transfiguration.php';
require 'Helpers/Evaluator.php';
