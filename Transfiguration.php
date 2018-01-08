<?php 
namespace Transfiguration;

use Transfiguration\Core\Lexer;
use Transfiguration\Core\Translator;
use Transfiguration\Core\Parser;


/**
* Transiguration main class.
*/
class Transfiguration {
	

	/* Initialise variables */
	public $requirebase = "";
	public $parser;
	public $hooks = [];

	/* Template data */
	public $data = [];
	public $path = "";
	public $html = "";
	

	/**
	* Instantiation of the class function.
	* @param $html : Transfiguration template html to transfigure.
	* @param $data : Variables for transfiguration template.
	* @param $path : Path for Include/Require base.
	*/
	public function __construct($html="", $data = [], $path="") {
		
		$this->html = $html;
		$this->data = $data;
		$this->path = $path;
		
	}


	/**
	* Function to set transfiguration template html.
	* @param $html : Transfiguration template html to transfigure.
	*/
	public function html($html="") {
		$this->html = $html;
		return $this;
	}


	/**
	* Function to set transfiguration template variables.
	* @param $data : Variables for transfiguration template.
	*/
	public function data($data=[]){
		$this->data = $data;
		return $this;
	}


	/**
	* Function to set path for Include/Require base.
	* @param $path : Path for Include/Require base.
	*/
	public function requirePath($path="") {
		$this->path = $path;
		return $this;
	}



	/**
	* Function to set hooks for transfiguration template.
	* @param $hook : Hook command.
	* @param $method : PHP anonymous function for hook.
	*/
	public function hook($hook="", $method="") {

		$this->hooks[] = [
			"hook" => $hook,
			"method" => $method
		];

		return $this;
	}


	/**
	* Function to get parser tokens.
	*/
	public function parserTokens() {
		$lexer = new Lexer($this->html);
		$this->parser = new Parser($lexer->exportTokens(), $this->data, $this->path, $this->hooks);
		return $this->parser->exportTokens();
	}


	/**
	* Function to export transfiguration template html as a string.
	*/
	public function export() {
		$translator = new Translator($this->parserTokens());
		return $translator->translate();
	}

	/**
	* Function to echo transfiguration template html.
	*/
	public function render() {
		$translator = new Translator($this->parserTokens());
		echo $translator->translate();
		return $this;
	}
	
}