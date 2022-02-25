<?php

class UserApiControllerEx extends ApiController {

	private $db;

	function __construct() {
		global $config;
		parent::__construct();

/*
		$this->RegisterRoute("/api/do_login.php", "login", "POST");
		$this->RegisterRoute("/api/do_register.php", "register", "POST");
		$this->RegisterRoute("/api/do_activate.php", "activateUser", "GET");
		$this->RegisterRoute("/api/get_user_detail.php", "getUserDetail", "POST");
		$this->RegisterRoute("/api/get_user_detail.php", "banUser", "POST");
		$this->RegisterRoute("/api/get_user_detail.php", "unbanUser", "POST");
		$this->RegisterRoute("/api/get_user_detail.php", "getBanlist", "POST");

*/
		$this->RegisterRoute("/api/user.php", "get", "GET");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}
}