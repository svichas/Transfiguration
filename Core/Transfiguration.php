<?php

class Transfiguration {
	private $parser;
	public function __construct($html, $data = []) {
		$lexer = new Lexer($html);
		$this->parser = new Parser($lexer->exportTokens(), $data);
	}
	public function export() {
		$translator = new Translator($this->parser->exportTokens());
		echo $translator->translate();
	}
}