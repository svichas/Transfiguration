<?php 
use PHPUnit\Framework\TestCase;

use Transfiguration\Transfiguration;

class TransfigurationTest extends TestCase {

	/**
	* Test Transfiguration For Loop Returns Corrent Result.
	*/
	public function testTransfigurationForLoop() {


		$transfiguration = new Transfiguration();
		$transfiguration->html('--start--{{ for $key : $item in $items }}{{ echo $item }}{{ endfor }}--end--');
		$transfiguration->data([
			"items" => ["item1","item2","item3"]
		]);


		// test if transfiguration export is the correct result.
		$this->assertEquals($transfiguration->export(), "--start--item1item2item3--end--");

	}

	/**
	* Test for Transfiguration echo command
	*/
	public function testTransfigurationEcho() {

		$transfiguration = new Transfiguration();
		$transfiguration->html('--start--{{echo "test".$item}}--end--');
		$transfiguration->data([
			"item" => "1"
		]);


		// test if transfiguration export is the correct result.
		$this->assertEquals($transfiguration->export(), "--start--test1--end--");

	}


	/**
	* Test for Transfiguration if else statment
	*/
	public function testTransfigurationIfElseStatment() {

		$transfiguration = new Transfiguration();
		$transfiguration->html('--start--{{if $item==1}}correct{{else}}error{{endif}}--end--');
		$transfiguration->data([
			"item" => "1"
		]);


		// test if transfiguration export is the correct result.
		$this->assertEquals($transfiguration->export(), "--start--correct--end--");

	}


	/**
	* Test for Transfiguration if elseif statment
	*/
	public function testTransfigurationIfElseIfStatment() {

		$transfiguration = new Transfiguration();
		$transfiguration->html('--start--{{if $item==2}}error{{elseif $item==1}}correct{{endif}}--end--');
		$transfiguration->data([
			"item" => "1"
		]);

		// test if transfiguration export is the correct result.
		$this->assertEquals($transfiguration->export(), "--start--correct--end--");

	}

	/**
	* Test if transfiguration comments are working correctly
	*/
	public function testTransfigurationComments() {

		$transfiguration = new Transfiguration();
		$transfiguration->html('--start--{# this is a comment #}--end--');
		$transfiguration->data([
			"item" => "1"
		]);

		// test if transfiguration export is the correct result.
		$this->assertEquals($transfiguration->export(), "--start----end--");


	}

}