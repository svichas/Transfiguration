<?php 
use PHPUnit\Framework\TestCase;

use Transfiguration\Core\Hooks;

class HooksTest extends TestCase {


	public function testCheckHooksMethodReturnsCorrectContent() {


		$hooks = new Hooks([
			"HEADER" => [
				"method" => function($content) {
					return "<h1>{$content}</h1>";
				}
			]
		]);

		
		$this->assertEquals($hooks->checkHooks([
			"type" => "ECHO",
			"content" => "",
			"tab" => 0
		], "test"), "");


		$this->assertEquals($hooks->checkHooks([
			"type" => "Header",
			"content" => "test",
			"tab" => 0
		], "test"), "<h1>test</h1>");

	}

}