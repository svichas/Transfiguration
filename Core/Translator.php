<?php
namespace Transfiguration\Core;

/**
* Class to translate tokens to plain html.
*/
class Translator {

	public $tokens = [];

	/**
	* Default method to get tokens
	* @param $tokens : tokens to translate to plain html
	*/
	public function __construct($tokens = []) {
		$this->tokens = $tokens;
	}

	public function translate() {

		// initialising html variable
		$html = "";


		// for each token 
		foreach ($this->tokens as $token) {

			// only html token types
			if ($token['type'] != "HTML") continue;

			// appending token content to html variable
			$html .= $token['content'];
		}

		//returing html
		return $html;

	}

}