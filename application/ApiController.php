<?php

class ApiController extends Controller {

	protected $request;
	protected $response;

	function __construct() {

		header("Access-Control-Allow-Origin: *");
		header('Access-Control-Allow-Methods: POST, GET');
		header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
		header("Content-Type: application/json; charset=UTF-8");

		$this->request = new Request(array_merge($_REQUEST));
		$this->response = new Response();
	}

	function RegisterRoute($route, $callback = null) {

		array_push($this->paths, $route, $callback);
	}

	function Route($path = null) {
		
		$response = $this->response;

		$route = $this->request->get("route");

		if (!empty($route) && ($index = array_search($route, $this->paths)) !== false) {
			echo call_user_func(array($this, $this->paths[$index]));
			return;
		}

		echo $response->notFound();
	}

	function isAuthenticated($request, $param) {
		return $this->isAuthorized($request, $param);
	}

	function isAuthorized($request, $param) {
		global $config;

		if (empty($request))
			return false;

		return (strcmp($config["privateApiKey"], $request->get($param)) == 0);
	}
	
	function invalidRequest($message = null) {
		$data = new Data();
		$logger = new Logger();
		$logger->error(empty($message) ? "Neplatná požadavka" : $message);
		$data->set("out", $logger);
		return json_encode($data->data);
	}
}