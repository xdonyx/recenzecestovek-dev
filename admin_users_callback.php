<?php

include ("./_config/config.php");

if (!RCViewController::isUserAdmin()) {
	header("Location: " . $config["baseUrl"]);
}

header('Content-Type: application/json; charset=utf-8');

if (is_null($_GET["user_id"]) || is_null($_GET["action"]) || is_null($_GET["value"])) {
	$log = new Logger();
	$log->error("Neplatná požadavka");
	unset($log->logs);
	unset($log->warnings);
	echo json_encode($log);
	return;
}

if ($_GET["user_id"] == $_SESSION["id"]) {
	$log = new Logger();
	$log->error("Nastavovat administrační práva můžete pouze ostatním uživatelům");
	unset($log->logs);
	unset($log->warnings);
	echo json_encode($log);
	return;
}

$request = [
	"api_key" => $config["privateApiKey"],
	"user_id" => (int) $_GET["user_id"],
	"action" => $_GET["action"],
	"admin" => (int) $_GET["value"],
];

$result = Controller::request($config["baseUrl"] . "/api/user.php?route=update", $request, "POST");

echo json_encode($result);