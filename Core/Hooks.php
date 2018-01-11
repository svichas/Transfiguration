<?php
namespace Transfiguration\Core;


class Hooks {

	public $hooks = [];

	public function __construct($hooks=[]) {
		$this->hooks = $hooks;
	}

	public function checkHooks($token=[],$content="") {

		if (!isset($this->hooks[$token['type']])) return false;

		$hook = $this->hooks[$token['type']];

		return call_user_func_array($hook['method'], [ "content" => $content ]);
	}

}