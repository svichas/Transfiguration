<?php 
namespace Transfiguration;

use Transfiguration\Core\Lexer;
use Transfiguration\Core\Translator;
use Transfiguration\Core\Parser;

class Transfiguration {
	
	public $requirebase = "";
	public $parser;
	public $hooks = [];

	public $data = [];
	public $path = "";
	public $html = "";
	
	public function __construct($html="", $data = [], $path="") {
		
		$this->html = $html;
		$this->data = $data;
		$this->path = $path;
		
	}

	public function html($html="") {
		$this->html = $html;
		return $this;
	}
	public function data($data=[]){
		$this->data = $data;
		return $this;
	}
	public function requirePath($path="") {
		$this->path = $path;
		return $this;
	}
	public function hook($hook="", $method="") {

		$this->hooks[] = [
			"hook" => $hook,
			"method" => $method
		];

		return $this;
	}

	public function parserTokens() {
		$lexer = new Lexer($this->html);
		$this->parser = new Parser($lexer->exportTokens(), $this->data, $this->path, $this->hooks);
		return $this->parser->exportTokens();
	}

	public function export() {
		$translator = new Translator($this->parserTokens());
		return $translator->translate();
	}

	public function render() {
		$translator = new Translator($this->parserTokens());
		echo $translator->translate();
		return $this;
	}
	
}