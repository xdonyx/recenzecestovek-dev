<?php

include ("./_config/config.php");

if (!RCViewController::isUserAdmin()) {
	header("Location: " . $config["baseUrl"]);
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET["ck_id"]) || !isset($_GET["action"]) || !isset($_GET["value"])) {
	$log = new Logger();
	$log->error("Neplatná požadavka");
	unset($log->logs);
	unset($log->warnings);
	echo json_encode($log);
	return;
}

$request = [
	"api_key" => $config["privateApiKey"],
	"ck_id" => (int) $_GET["ck_id"],
	"action" => $_GET["action"],
	"value" => (int) $_GET["value"],
];

$result = Controller::request($config["baseUrl"] . "/api/cestovky.php?route=update", $request, "POST");

echo json_encode($result);