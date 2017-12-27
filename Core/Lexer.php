<?php 

namespace Transfiguration\Core;

Class Lexer {


	public $blockEnds = [
		"FOR"   => "ENDFOR",
		"WHILE" => "ENDWHILE",
		"IF"    => "ENDIF"
	];
	public $html   = "";
	public $tokens = [];

	public $tab = 0;

	function __construct($html = "") {
		$this->html = $html;
		$this->createTokens();
		//print_r($this->tokens);
	}

	private function createTokens() {

		$openCodeBlock = false;
		$skipNext = false;
		$openCommentBlock = false;

		$strlen = strlen($this->html);
		$block = "";

		for( $i = 0; $i <= $strlen; $i++ ) {

			// get current char and next char
			$char = substr( $this->html, $i, 1);
			$nextchar = ($strlen >= $i) ? substr( $this->html, $i+1, 1) : "";

			// Code to skip next char.
			if ($skipNext) {
				$skipNext = false;
				continue;
			}

			//Open code block
			if (!$openCodeBlock && $char == "{" && $nextchar == "{") {
				$skipNext = true;
				$openCodeBlock = true;
				$this->createToken("HTML", $block);
				$block = "";
			} else if ($openCodeBlock && $char == "}" && $nextchar == "}") {
				$skipNext = true;
				$openCodeBlock = false;
				$this->doToken($block);
				$block = "";
			} else if (!$openCodeBlock 
			&& !$openCommentBlock 
			&& $char == "{" 
			&& $nextchar == "#") { //Open comment block
				$skipNext = true;
				$openCommentBlock = true;
			} else if ($openCommentBlock 
			&& $char == "#" 
			&& $nextchar == "}") { //Close comment block
				$skipNext = true;
				$openCommentBlock = false;
			} else if (!$openCodeBlock && !$openCommentBlock) {
				$block .= $char;
			} else if ($openCodeBlock && !$openCommentBlock) {
				$block .= $char;
			}

		}

		// Create last block
		$this->createToken("html", $block);
		$block = "";

	}

	function exportTokens() {
		return $this->tokens;
	}
	
	private function createToken($type="", $content="") {

		$type = strtoupper($type);
		$ctab = $this->tab;

		
		if (in_array($type, array_keys($this->blockEnds))) {
			$this->tab++;
		}

		if (in_array($type, $this->blockEnds) && $ctab > 0) {
			$this->tab--;
			$ctab--;
		}

		/* Special case for IF statement */

		if ($type == "ELSE" || $type == "ELSEIF") {
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
