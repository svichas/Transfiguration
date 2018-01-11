<?php
namespace Transfiguration\Helpers;


/*
Evaluator class for evaluating strings.
*/
class Evaluator {

	// Variable for binding variables in template
	public $data = [];

	public function __construct($data=[]) {
		// setting data
		$this->data = $data;
	}

	public function appendData($data = []) {
		// appending data
		$this->data[] = $data;
	}

	/**
	* Method to evaluate a string expression.
	* @param $expression : expression to evaluate
	* @param $data : data to bind in expression
	*/
	public function evaluate($expression, $data = []) {

		try {

			// extracting variables in order for template to use.
			extract($this->data);
			extract($data);
			
			//taking first part for security reasons
			$expression_arr = explode(";" , $expression);
			$expression = $expression_arr[0];
		
			// evaluating string with php function eval.
			@eval("\$__result__ = ".$expression.";");

			// returing result of eval function.
			return $__result__;
		} catch (Exception $e) {
			// incase of error die and print error.
			die($e);
		}
	}

	/**
	* Function to set a variable
	* @param $varname : variable name to set.
	* @param $varcontent : content of variable to set.
	*/
	public function setVar($varname="", $varcontent="") {
		// formating var name
		$varname = $this->evalVarName($varname, true);
		// appending var with name and content in variables.
		$this->data[$varname] = $this->evaluate($varcontent);
		return true;
	}


	/**
	* Function to format a variable name
	* @param $varname : variable name
	* @param $removedollar : remove dollar from variable name
	*/
	public function evalVarName($varname = "", $removedollar = false) {
		// removing spaces
		$varname = str_replace(' ', '', $varname);
		// removing dollar symbol
		if ($removedollar) $varname = ltrim($varname, "$");

		return $varname;
	}

	/**
	* Function for (for loop) returning for tokens
	* @param $for_token : tokens of for loop
	* @param $for_array : array index of for loop
	* @param $for_var
	* @param $for_key
	*/
	public function doFor($for_tokens = [], $for_array = "", $for_var = "", $for_key="") {

		// formating variables
		$for_array = $this->evalVarName($for_array, true);
		$for_var   = $this->evalVarName($for_var, true);
		$for_key   = $this->evalVarName($for_key, true);

		// initialising return tokens
		$return_fortokens = [];

		// simulating for loop
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


		// returing result
		return $return_fortokens;

	}

}
