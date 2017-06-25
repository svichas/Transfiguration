<?php
namespace Transfiguration\Core;

class Translator {

	public $tokens = [];

	public function __construct($tokens = []) {
		$this->tokens = $tokens;
	}

	public function translate() {

		$html = "";

		foreach ($this->tokens as $token) {

			if ($token['type'] != "HTML") continue;

			$html .= $token['content'];
		}

		return $html;

	}

}