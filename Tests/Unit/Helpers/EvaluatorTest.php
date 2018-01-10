<?php 
use PHPUnit\Framework\TestCase;

use Transfiguration\Helpers\Evaluator;

class EvaluatorTest extends TestCase {


	/**
	* Test evaluator string evaluation
	*/
	public function testEvaluateMethodEvaluatesStringCorrectly() {

		// create evaluator object and test result.
		$evaluator = new Evaluator([
			"testVar" => "test value"
		]);

		$evaluator_result = $evaluator->evaluate("\$testVar . ' ' . \$testVar2", [
			"testVar2" => "test value 2"
		]);

		// test evaluator evaluation result
		$this->assertEquals($evaluator_result, "test value test value 2");

	}




}