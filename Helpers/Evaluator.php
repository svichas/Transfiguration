<?php
namespace Transfiguration\Helpers;

class Evaluator {

	public $data = [];

	public function __construct($data=[]) {
		$this->data = $data;
	}

	public function appendData($data = []) {
		$this->data[] = $data;
	}

	public function evaluate($expression, $data = []) {
		try {

			extract($this->data);
			extract($data);
			
			//taking first part for security reasons
			$expression_arr = explode(";" , $expression);
			$expression = $expression_arr[0];
	
			@eval("\$__result__ = ".$expression.";");

			return $__result__;
		} catch (Exception $e) {
			die($e);
		}
	}

	public function setVar($varname="", $varcontent="") {
		$varname = $this->evalVar($varname, true);
		$this->data[$varname] = $this->evaluate($varcontent);
	}

	public function evalVar($varname = "", $removedollar = false) {
		$varname = str_replace(' ', '', $varname);
		if ($removedollar) $varname = ltrim($varname, "$");
		return $varname;
	}


	public function doFor($for_tokens = [], $for_array = "", $for_var = "", $for_key="") {

		$for_array = $this->evalVar($for_array, true);
		$for_var   = $this->evalVar($for_var, true);
		$for_key   = $this->evalVar($for_key, true);

		$return_fortokens = [];

		foreach ($this->data[$for_array] as $__key__ => $__var__) {

			for ($i=0;$i<count($for_tokens);$i++) {
				if ($for_key != "") {
					$for_tokens[$i]['data'] = [
						$for_var => $__var__,
						$for_key => $__key__
					];
				} else {
					$for_tokens[$i]['data'] = [
						$for_var => $__var__
					];
				}
			}

			foreach ($for_tokens as $tok) {
				$return_fortokens[] = $tok;
			}

		}

		return $return_fortokens;

	}

}
