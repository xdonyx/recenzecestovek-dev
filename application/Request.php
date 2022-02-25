<?php

class Request extends Data {

	function __construct($body) {
		parent::__construct($body);
	}

	function isAuthorized($param = "api_key") {
		global $config;
		
		if (empty($this->data))
			return false;

		return (strcmp($config["privateApiKey"], $this->get($param)) == 0);
	}

	function isLocal() {
		return !strcmp($_SERVER["SERVER_ADDR"], $_SERVER["REMOTE_ADDR"]);
	}
}