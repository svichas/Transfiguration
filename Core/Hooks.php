<?php
namespace Transfiguration\Core;

/**
* Class for transfiguration hooks to extend transfiguration functionality.
*/
class Hooks {

	public $hooks = [];

	/**
	* Main method to parse hooks to this class.
	*/
	public function __construct($hooks=[]) {
		$this->hooks = $hooks;
	}

	/**
	* Method to check a token if matches with any hook and return hook result
	*/
	public function checkHooks($token=[],$content="") {

		// capitalize token type
		$token['type'] = strtoupper($token['type']);

		// if token type not exists in hooks leave method.
		if (!isset($this->hooks[$token['type']])) return false;

		// get token hook if matches
		$hook = $this->hooks[$token['type']];

		// return method result
		return call_user_func_array($hook['method'], [ "content" => $content ]);
	}

}