<?php

abstract class Controller {

	protected $paths = Array();

	public static function request($target, $request, $method) {

		if ($method == "GET") {
			return Controller::getRequest($target, $request);
		}

		$opts = array(
			'http' =>
				array(
					'method'  => $method,
					'header'  => 'Content-Type: application/x-www-form-urlencoded',
					'content' => http_build_query($request)
				),
			'https' =>
				array(
					'method'  => $method,
					'header'  => 'Content-Type: application/x-www-form-urlencoded',
					'content' => http_build_query($request)
				)
		);

		$context  = stream_context_create($opts);

		return json_decode(file_get_contents($target, false, $context));
	}

	private static function getRequest($target, $request) {
		
	    $route = null;
		if (isset($_GET["route"])) {
			$route = $_GET["route"];
			unset($_GET["route"]); 
		}

		$result = json_decode(file_get_contents($target . "&" . http_build_query($request), false));
		$_GET["route"] = $route;

		return $result;
	}
}