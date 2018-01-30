<?php 
use PHPUnit\Framework\TestCase;

use Transfiguration\Transfiguration;

class TransfigurationTest extends TestCase {

	/**
	* Test Transfiguration For Loop Returns Corrent Result.
	*/
	public function testTransfigurationForLoop() {


		$transfiguration = new Transfiguration();
		$transfiguration->html("--start--{{ for $key : $item in $items }}{{ echo $item }}{{ endfor }}--end--");
		$transfiguration->data([
			"items" => ["item1","item2","item3"]
		]);

		// test if transfiguration export is the correct result.
		$this->assertEquals($transfiguration->export(), "--start--item1item2item3--end--");


	}


}