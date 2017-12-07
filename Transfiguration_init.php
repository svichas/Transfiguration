<?php 

// check if is already difined
if (class_exists("Transfiguration")) {
	return;
}

/*
Require all files for transfiguration
*/
require 'Core/Lexer.php';
require 'Core/Parser.php';
require 'Core/Translator.php';
require 'Helpers/Evaluator.php';
require 'Transfiguration.php';
