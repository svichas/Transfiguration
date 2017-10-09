<?php 

require '../transfiguration.php';
$data = [
	"awesome" => 1+2,
	"tests" => [
		"makis",
		"giorgos",
		"steve",
		"kastas"
	]
];


$trans = new Transfiguration(file_get_contents("template.html"), $data, "inc/");

$trans->hook("header", function($content="") {
	return "<h1>$content</h1>";
});

$trans->render();