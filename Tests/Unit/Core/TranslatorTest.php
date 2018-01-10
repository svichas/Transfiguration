<?php 
use PHPUnit\Framework\TestCase;


use Transfiguration\Core\Translator;


class TranslatorTest extends TestCase {

	/**
	* Test translator translates correctly tokens
	*/
	public function testTranslatorReturnsCorrectTranslationFromTokens() {

		// Create test tokens to translate
		$translator = new Translator([
			[
				"type" => "HTML",
				"content" => "hello, world!",
				"tab" => ""
			],
			[
				"type" => "ECHO",
				"content" => "1",
				"tab" => ""
			]
		]);

		// check translation result.
		$this->assertEquals($translator->translate(), "hello, world!");

	}

}