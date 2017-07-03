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
$trans->render();