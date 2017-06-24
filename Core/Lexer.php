<?php 

namespace Transfiguration\Core;

Class Lexer {

	public $html   = "";
	public $tokens = [];

	public $tab = 0;

	function __construct($html = "") {
		$this->html = $html;
		$this->createTokens();
	}

	private function createTokens() {

		$openCodeBlock = false;
		$skipNext = false;

		$strlen = strlen($this->html);
		$prevchar = "";
		$codeBlock = "";
		$htmlBlock = "";

		for( $i = 0; $i <= $strlen; $i++ ) {

			$char = substr( $this->html, $i, 1);
			$nextchar = ($strlen >= $i) ? substr( $this->html, $i+1, 1) : "";

			if ($skipNext) {
				$skipNext = false;
				continue;
			}

			//Open code block
			if (!$openCodeBlock && $char == "{" && $nextchar == "{") {
				$skipNext = true;
				$openCodeBlock = true;
				$this->createToken("HTML", $htmlBlock);
				$htmlBlock = "";
			} else if ($openCodeBlock && $char == "}" && $nextchar == "}") {
				$skipNext = true;
				$openCodeBlock = false;
				$this->doToken($codeBlock);
				$codeBlock = "";
			} else if (!$openCodeBlock) {
				$htmlBlock .= $char;
			} else if ($openCodeBlock) {
				$codeBlock .= $char;
			}


			$prevchar = $char;
		}

		$this->createToken("html", $htmlBlock);
		$htmlBlock = "";

	}

	function exportTokens() {
		return $this->tokens;
	}
	
	private function createToken($type="", $content="") {

		$type = strtoupper($type);
		$ctab = $this->tab;


		if ($type == "IF") {
			$this->tab++;
		}

		if ($type == "ENDIF" && $ctab > 0) {
			$this->tab--;
			$ctab--;
		}
		
		if ($type == "ELSE" && $ctab > 0) {
			$ctab--;
		}

		if ($type == "ELSEIF" && $ctab > 0) {
			$ctab--;
		}

		if ($type == "FOR") {
			$this->tab++;
		}
		if ($type == "ENDFOR" && $ctab > 0) {
			$this->tab--;
			$ctab--;
		}

		if ($type == "WHILE") {
			$this->tab++;
		}
		if ($type == "ENDWHILE" && $ctab > 0) {
			$this->tab--;
			$ctab--;
		}

		$this->tokens[] = [
			"type" => $type,
			"content" => $content,
			"tab" => $ctab
		];


	
	}

	private function doToken($content="") {
		$token_type = $this->findCodeType($content);
		$token_content = $this->findCodeContent($content);
		$this->createToken($token_type, $token_content);
	}

	private function findCodeType($codeBlock) {
		$codeBlock = ltrim(rtrim($codeBlock, " ")," ");
		$arr = explode(" ", $codeBlock);
		return strtoupper($arr[0]); 
	}
	private function findCodeContent($codeBlock) {
		$codeBlock = ltrim(rtrim($codeBlock, " ")," ");
		$arr = explode(" ", $codeBlock);
		$content = "";
		$i = 0;
		foreach ($arr as $cnt) {
			if ($i!=0) {
				$content .= " " .$cnt;
			}
			$i += 1;
		}
		return ltrim($content, " ");
	}


}