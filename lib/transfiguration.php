<?php
/*

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
  protected $html = "";


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


  #function to replace blocks with data
  public function block($blockName, $values, $withbraces=true, $caseSensitive=false) {

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
          #replacing tags with values and checking for $caseSensitive
          if ($caseSensitive) $string = str_replace($node,$value[$key],$string);
          else $string = str_ireplace($node,$value[$key],$string);

          //$string = ($caseSensitive) ? str_replace($node,$value[$key],$string) : str_ireplace($node,$value[$key],$string);

        }
      }
      #replacing html
      $this->html = substr_replace($this->html, $string, stripos($this->html, $blockName),strlen($full));
    }

    return true;
  } #end of block() function


  #function to replace values from the html with data
  public function replaceValues($values = [], $withbraces=true, $caseSensitive=false) {
    $val_keys = array_keys($values);
    foreach ($val_keys as $val_key) {
      if ($caseSensitive) $this->html = str_replace(($withbraces==true) ? "{{".$val_key."}}" : $val_key,$values[$val_key],$this->html);
      else $this->html = str_ireplace(($withbraces==true) ? "{{".$val_key."}}" : $val_key,$values[$val_key],$this->html);
    }
    return true;
  } #end of replaceValues() function


  #domAdd() function for adding dom elements
  #$dom array var for elements to add
  # $dom systax
  /*
  $dom = [
    "append_tag_name" => [
      "tagname" => "tagname of the element", #this array index is required
      "value" => "value of the element",
      "class" => "class of the element",
      "attr" => ""
      ...
    ],
    #for example.
    "head" => [
      "tagname" => "link",
      "href" => "link/to/css",
      "rel" => "stylesheet"
    ],
    ...
  ];

  */
  public function addDom($dom = [],$append = true) {
    #checking if $dom is array
    if (is_array($dom)) {
      $step = 0;
      $dom_keys = array_keys($dom);
      foreach ($dom as $node) {
        $to = strtolower($dom_keys[$step]);
        $to = ($append) ? "</".$to.">" : "<".$to.">";

        if (stripos($this->html,$to) > 0) {

          $html = "";
          $attr = "";

          foreach (array_keys($node) as $key) {
            if (!in_array($key,["tagname","html","value"])) $attr .= $key."=\"".$node[$key]."\" ";
            else if (in_array(strtolower($key),["html","value"])) $html = $node[$key];
          }
          $attr = rtrim($attr," ");
          $tagname = $node["tagname"];

          $str = "<$tagname $attr>";
          if (!in_array(strtolower($tagname), ["link"])) $str = $str . $html . "</$tagname>";
          $this->html = str_ireplace($to, ($append) ? $str.$to : $to.$str, $this->html);
        }
        $step += 1;
      }

    } else return false; #if $dom is not array
  } #end of domAdd() function


  #function to export html
  public function exportHtml() {
    return $this->html;
  } #end of exportHtml() function


  #function to load html
  public function loadHtml($html = "") {
    $this->html = $html;
  } #end of loadHtml() function


}
