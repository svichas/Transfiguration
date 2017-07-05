<?php
namespace Transfiguration\Core;

use Transfiguration\Helpers\Evaluator;

class Parser {

	public $toks = [];
	public $tokens = [];
	public $Evaluator;
	public $blockEnds = [
		"FOR" => "ENDFOR",
		"WHILE" => "ENDWHILE",
		"IF" => "ENDIF"
	];
	public $base_include_path = "";

	function __construct($tokens = [], $data=[], $path="") {
		$this->toks = $tokens;
		$this->tokens = $tokens;
		$this->Evaluator = new Evaluator($data);
		$this->base_include_path = $path;

		$this->ParseTokens();
	}


	private function parseTokens() {

		$i=0;
		while ($i<count($this->tokens)) {

			$token = $this->tokens[$i];
			$token_data = isset($token['data']) ? $token['data'] : [];

			switch (strtoupper($token['type'])) {

				case 'INCLUDE':

					$this->include($token,$i);
					break;
				
				case 'REQUIRE':

					$this->include($token,$i);
					break;

				case 'ECHO':

					$this->setToken($i, $this->Evaluator->evaluate($token["content"],$token_data));
					break;

				case 'PRINT':
					$this->setToken($i, $this->Evaluator->evaluate($token["content"],$token_data));
					break;

				case "VAR":
					$part = 1;
					$varname = "";
					$varcontent = "";
					for( $c = 0; $c <= strlen($token['content']); $c++ ) {
		    			$char = substr( $token['content'], $c, 1);

		    			if ($part == 1) {
		    				if ($char == "=") {
		    					$part = 2;
		    				} else {
		    					$varname .= $char;
		    				}
		    			} elseif ($part == 2) {
		    				$varcontent .= $char;
		    			}

					}
					$this->Evaluator->setVar($varname, $varcontent);
					$this->removeToken($i);

					break;

				case "FOR":
					$for_tokens = $this->findEnd($token, $i);

					//$for_token
					//function doFor($for_tokens = [], $for_array = "", $for_var = "", $for_key="")

					$token_content_array = preg_split("/in/i", $token['content']);

					$for_var = $token_content_array[0];

					$value_array = explode(":", $token_content_array[1]);

					$key   = "";
					$for_var_array = "";

					if (count($value_array) == 2) {
						$key   = $value_array[0];
						$for_var_array = $value_array[1];
					} else if (count($value_array) == 1) {
						$for_var_array = $value_array[0];
					}

					$return_fors = $this->Evaluator->doFor($for_tokens, $for_var_array, $for_var, $key);
					$this->appendTokens($i, $return_fors);

					//recompile
					$i = 0;


					break;


				case "IF":

					$found_endif = false;
					$found_executable = false;
					$executable_position = 0;

					$step = $i;
					while (!$found_endif&&!$found_executable&&$step<100000) {

						$if_token = $this->tokens[$step];

						if ($if_token['tab']==$token['tab']) {

							switch (strtoupper($if_token['type'])) {
								case 'IF':

									if ($this->Evaluator->evaluate($if_token['content'], $token_data)) {
										$executable_position = $step;
										$found_executable = true;
									}

									break;

								case 'ELSEIF':
									if ($this->Evaluator->evaluate($if_token['content'], $token_data)) {
										$executable_position = $step;
										$found_executable = true;
									}

									break;
								case "ELSE":
									$executable_position = $step;
									$found_executable = true;

									break;
								case "ENDIF":
									$found_endif = true;
									break;

							}

						}

						$step++;
					}

					/*
						Parse if
					*/
					$found_endif = false;
					$step = $i;
					$show = false;

					while (!$found_endif&&$step<100000) {

						$if_token = $this->tokens[$step];

						if ($if_token['tab']==$token['tab']) {

							switch (strtoupper($if_token['type'])) {
								case 'IF':

									if ($show) $show = false;

									if ($executable_position == $step) $show = true;

									$this->removeToken($step);

									break;

								case 'ELSEIF':

									if ($show) $show = false;

									if ($executable_position == $step) $show = true;

									$this->removeToken($step);

									break;
								case "ELSE":

									if ($show) $show = false;

									if ($executable_position == $step) $show = true;

									$this->removeToken($step);

									break;
								case "ENDIF":

									if ($show) $show = false;

									$found_endif = true;

									$this->removeToken($step);

									break;
							}

						}

						if (!$show) {
							$this->removeToken($step);
						}

						$step++;

					}



					break;

				default:
					# code...
					break;
			}

			$i += 1;
		}



	}

	public function include($token=[], $i=-1) {
		
		$token_data = isset($token['data']) ? $token['data'] : [];

		$require_path = $this->Evaluator->evaluate($token['content'], $token_data);
		$require_path = $this->base_include_path.  $require_path;

		if (file_exists($require_path)) {
			$content = file_get_contents($require_path);
			$temp_data = array_merge($this->Evaluator->data, $token_data);
			$transfig = new \Transfiguration($content, $temp_data, $this->base_include_path);
			$inc_tokens = $transfig->parser->exportTokens();
			$this->appendTokens($i,$inc_tokens);
		}
	}

	public function findEnd($token=[], $position) {

		$start_keyword = $token['type'];
		$end_keyword   = $this->blockEnds[$start_keyword];

		$found_end     = false;
		$loop_tokens   = [];
		$step          = $position;

		while (!$found_end && isset($this->tokens[$step]) && $step < 100000) {

			$loop_token = $this->tokens[$step];

			if ($loop_token['tab'] == $token['tab']) {
				if ($loop_token['type'] == $end_keyword) $found_end = true;
			}

			if ($step != $position && !$found_end) {
				$loop_tokens[] = $loop_token;
			}
			$this->removeToken($step);
			$step++;
		}


		return $loop_tokens;
	}

	private function appendTokens($position=0, $token=[]) {

		$array_part1 = array_slice($this->tokens, 0, $position);
		$array_part2 = array_slice($this->tokens, $position+1, count($this->tokens)-1);

		$ret_array = array_merge($array_part1, $token);
		$ret_array = array_merge($ret_array, $array_part2);

		$this->tokens = $ret_array;
		//print_r($this->tokens);

	}

	private function removeToken($position) {
		$this->tokens[$position] = [
			"type" => "HTML",
			"content" => "",
			"tab" => 0
		];
	}

	private function setToken($position, $value) {
		$this->tokens[$position] = [
			"type" => "HTML",
			"content" => $value,
			"tab" => 0
		];
	}

	function exportTokens() {
		return $this->tokens;
	}

}
