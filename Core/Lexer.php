<?php 
namespace Transfiguration\Core;



/**
* Class for translating plain text (Transfiguration code) to tokens.
*/
Class Lexer {


	// Initialising variables
	public $blockEnds = [
		"FOR"   => "ENDFOR",
		"WHILE" => "ENDWHILE",
		"IF"    => "ENDIF"
	];
	public $html   = "";
	public $tokens = [];

	// Tab for closing tags (if, for, etc)
	public $tab = 0;


	/**
	* Default method getting html.
	*/
	function __construct($html = "") {
		// getting html
		$this->html = $html;
		// calling method to create tokens from html
		$this->createTokens();
	}


	/**
	* Method for translating plain text (Transfiguration code) to tokens.
	*/
	public function createTokens() {

		$openCodeBlock = false; // boolean flag for code block
		$skipNext = false; // skip next character
		$openCommentBlock = false; // boolean flag for comments 


		// string length
		$strlen = strlen($this->html);
		// current block contents
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
			if (!$openCodeBlock 
				&& !$openCommentBlock
				&& $char == "{" 
				&& $nextchar == "{") {
				$skipNext = true;
				$openCodeBlock = true;
				$this->createToken("HTML", $block);
				$block = "";
			} else if ($openCodeBlock 
				&& !$openCommentBlock
				&& $char == "}" 
				&& $nextchar == "}") {
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


	/**
	* Method to return html tokens
	*/
	function exportTokens() {
		return $this->tokens;
	}
	

	/**
	* Method to create a single token.
	* @param $type : token type
	* @param $content : token content
	*/
	public function createToken($type="", $content="") {

		$type = strtoupper($type);
		$ctab = $this->tab;
		
		// handling tabs
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


		// appending token to tokens
		$this->tokens[] = [
			"type" => $type,
			"content" => $content,
			"tab" => $ctab
		];

		return true;
	
	}

	/**
	* Method to create token
	* @param $content : code block contents
	*/
	public function doToken($content="") {
		$token_type = $this->findCodeType($content);
		$token_content = $this->findCodeContent($content);
		$this->createToken($token_type, $token_content);
	}


	/**
	* Method to find token type
	* @param $codeBlock : code block contents
	*/
	public function findCodeType($codeBlock) {
		$codeBlock = ltrim(rtrim($codeBlock, " ")," ");
		$arr = explode(" ", $codeBlock);
		return strtoupper($arr[0]); 
	}

	/**
	* Method to find token content
	* @param $codeBlock : code block contents
	*/
	public function findCodeContent($codeBlock) {
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
