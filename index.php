<?php
require 'lib/transfiguration.php';

#creating transfiguration object
$transfiguration = new transfiguration();

#load html
$transfiguration->loadHtml(file_get_contents("template.html"));

#replacing values with data
$values = array(
  "name" => "steve",
  "old" => "19 years old"
);
$transfiguration->replaceValues($values);

#adding html elements
$elements = array(
  "appendto" => "head",
  "tagname" => "link",
  "rel" => "stylesheet",
  "href" => "link/to/css"
);
$transfiguration->addElement($elements);

$element = array(
  "appendto" => "body",
  "tagname" => "p",
  "class" => "paragraph",
  "html" => "This was added <b>later</b>"
);
$transfiguration->addElement($element);


#creating code blocks
$values = array(
  [
    "link" => "https://github.com/svichas/transfiguration",
    "text" => "GitHub"
  ],
  [
    "link" => "http://facebook.com/",
    "text" => "Facebook"
  ],
  [
    "link" => "http://youtube.com/",
    "text" => "Youtube"
  ]
);
$transfiguration->block("links",$values);

#creating if block
$transfiguration->showBlock("post",false);
$transfiguration->showBlock("page",true);

#Get values
print $transfiguration->getValue("var1");
print "<br>";
print $transfiguration->getValue("name");
print "<br>";
print $transfiguration->getValue("theme_name");


#minify HTML
//$transfiguration->minify();

#print transfigured html
print $transfiguration->exportHtml();
