<?php
/*

https://github.com/svichas/Transfiguration

MIT License

Copyright (c) 2017 stefanos vichas

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

class transfiguration {

  #var for html
  private $html = "";
  private $vars = [];

  function __construct($arg = "") {
    if (isset($arg)&&$arg!="") $this->html = $arg;
  } #end of __construct() function


  #function to get a string between tow strings
  # $start var for start word of string
  # $end var for end word of string
  # $caseSensitive for case sensitivity between $start and $end word
  private function gethtmlbetween($start, $end, $caseSensitive=false){
      $html = " " . $this->html;
      $ini = ($caseSensitive) ? strpos($html, $start) : stripos($html, $start);
      if ($ini == 0) return '';
      $ini += strlen($start);
      $len = ($caseSensitive) ? strpos($html, $end, $ini) - $ini : stripos($html, $end, $ini) - $ini;
      return substr($html, $ini, $len);
  } #end of gethtmlbetween() function


  private function constructElement($tagname="", $attributes="", $html="", $value = "") {

    $close = true;
    $value = htmlspecialchars($value);
	  
    $not_closing_tags = [
      "link",
      "meta",
      "input",
      "hr",
      "br",
      "source",
      "param",
      "img",
      "embed",
      "command",
      "col",
      "base",
      "area",
    ];

    if (in_array(strtolower($tagname), $not_closing_tags)) $close = false;

    $element = ($close) ? "<{$tagname}{$attributes}>{$html}{$value}</{$tagname}>" : "<{$tagname}{$attributes} />";
	
    return $element;
  } #end of constructElement() function


  private function appendElement($element, $appendto) {
    $appendto = "</$appendto>";
    $element = $element.$appendto;
    $this->html = str_ireplace($appendto, $element, $this->html);
  } #end of appendElement() function


  #function to replace blocks with data
  public function block($blockName, $values, $withbraces=true, $caseSensitive=false, $xss = false) {

    #block names
    $endBlockName = ($withbraces) ? "{{/".$blockName."}}" : "/".$blockName;
    $blockName = ($withbraces) ? "{{".$blockName."}}" : $blockName;

    #looping for each node starting with $endBlockName and ending with $blockName with in the html and also checking $caseSensitive
    while (($caseSensitive) ? (strpos($this->html, $blockName) > 0 && strpos($this->html, $endBlockName) > 0) : (stripos($this->html, $blockName) > 0 && stripos($this->html, $endBlockName) > 0)) {

      #getiing string between $blockName and $endBlockName with gethtmlbetween()
      $inner = $this->gethtmlbetween($blockName, $endBlockName, $caseSensitive);
      #full node
      $full = $blockName.$inner.$endBlockName;

      $string = "";
      foreach ($values as $value) {
        $string .= $inner;
        $value_keys = array_keys($value);
        foreach ($value_keys as $key) {
          #node
          $node = ($withbraces) ? "{{".$key."}}" : $key;

          $val = ($xss) ? htmlspecialchars($value[$key],ENT_QUOTES) : $value[$key];

          #replacing tags with values and checking for $caseSensitive
          if ($caseSensitive) $string = str_replace($node,$val,$string);
          else $string = str_ireplace($node,$val,$string);

          //$string = ($caseSensitive) ? str_replace($node,$value[$key],$string) : str_ireplace($node,$value[$key],$string);

        }
      }
      #replacing html
      $this->html = substr_replace($this->html, $string, stripos($this->html, $blockName),strlen($full));
    }

    return true;
  } #end of block() function


  public function getValue($name = "") {
    $count = 1;
    while (stripos($this->html, "{#") > 0 && stripos($this->html, "#}") > 0 && $count <= 200) {

      $content = $this->gethtmlbetween("{#","#}");
      $full_content = "{#".$content."#}";
      $this->html = str_ireplace($full_content, "", $this->html);

      $var_name = trim(substr($content, 0, stripos($content, "=")), " ");
      $var_value = trim(substr($content, stripos($content, "=")+1,-1), " ");

      $this->vars[$var_name] = $var_value;

      $count += 1;

    }

    return (isset($this->vars[$name])) ? $this->vars[$name] : "";

  }


  #function to replace values from the html with data
  public function replaceValues($values = [], $withbraces=true, $caseSensitive=false, $xss = false) {
    $val_keys = array_keys($values);
    foreach ($val_keys as $val_key) {

      $val = ($xss) ? htmlspecialchars($values[$val_key], ENT_QUOTES) : $values[$val_key];

      if ($caseSensitive) $this->html = str_replace(($withbraces==true) ? "{{".$val_key."}}" : $val_key,$val,$this->html);
      else $this->html = str_ireplace(($withbraces==true) ? "{{".$val_key."}}" : $val_key,$val,$this->html);
    }
    return true;
  } #end of replaceValues() function


  #domAdd() function for adding dom elements
  public function addElement($element = []) {
    #checking if $dom is array
    if (is_array($element)) {


      $data = [
        "append" => "",
        "tag" => "",
        "html" => "",
        "value" => "",
        "attrs" => ""
      ];
      $element_keys = array_keys($element);

      foreach ($element_keys as $element_key) {
        $element_key = strtolower($element_key);
        if ($element_key == "appendto" || $element_key == "append") {
          $data["append"] = $element[$element_key];
        } else if ($element_key == "html") {
          $data["html"] = $element[$element_key];
        } else if ($element_key == "value") {
          $data["value"] = $element[$element_key];
        } else if ($element_key == "tagname" || $element_key == "tag") {
          $data["tag"] = $element[$element_key];
        } else {
          $data["attrs"] .= " ".$element_key."='".$element[$element_key]."'";
        }
      }

      $element = $this->constructElement($data['tag'], $data["attrs"], $data["html"], $data["value"]);
      $this->appendElement($element, $data['append']);

    } else return false; #if $dom is not array
  } #end of addDom() function


  public function showBlock($blockName = "", $show=false, $caseSensitive = false) {

    #append and preppend braces tp $blockName if required
    $startBlock = "{%$blockName%}";
    $endBlock = "{%/$blockName%}";

    while (($caseSensitive) ? (strpos($this->html, $startBlock) > 0 && strpos($this->html, $endBlock) > 0) : (stripos($this->html, $startBlock) > 0 && stripos($this->html, $endBlock) > 0)) {
      $innerHtml = $this->gethtmlbetween($startBlock,$endBlock);
      $full = $startBlock.$innerHtml.$endBlock;
      $string = ($show) ? $innerHtml : "";
      $this->html = substr_replace($this->html, $string, stripos($this->html, $startBlock),strlen($full));
    }

    return true;
  } #end of showBlock() function

  #Minify function to minify html
  public function minify() {
    $search = [
        '/\>[^\S ]+/s',
        '/[^\S ]+\</s',
        '/(\s)+/s',
        '/<!--(.|\s)*?-->/'
    ];
    $replace = [
        '>',
        '<',
        '\\1',
        ''
    ];
    $this->html = preg_replace($search, $replace, $this->html);
    return true;
  } #end of minify() function

  #function to export html
  public function exportHtml() {
    return $this->html;
  } #end of exportHtml() function

  #function to save html
  public function saveHtml() {
    return $this->html;
  } #end of saveHtml() function

  #function to load html
  public function loadHtml($html = "") {
    $this->html = $html;
  } #end of loadHtml() function

}
