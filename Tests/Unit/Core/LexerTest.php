<?php 
use PHPUnit\Framework\TestCase;

use Transfiguration\Core\Lexer;

class LexerTest extends TestCase {


	public function testFindCodeTypeReturnsCorrectCodeType() {
		// create lexer object.
		$lexer = new Lexer;

		// test for correct result.
		$this->assertEquals($lexer->findCodeType("echo 'hello, world'"), "ECHO");
	}

	public function testFindCodeContentReturnsCorrectContent() {


		// create lexer object.
		$lexer = new Lexer;

		// test for correct result.
		$this->assertEquals($lexer->findCodeContent("echo 'hello, world'"), "'hello, world'");

	}

}