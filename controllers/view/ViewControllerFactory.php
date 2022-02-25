<?php

class ViewControllerFactory {

	private function __construct() {
	}

	public static function getController($route) {

		$controllers = [
			"User" => [
				"aktivace",
				"aktivacni-email",
				"login",
				"moje-recenze",
				"nastaveni",
				"odhlasit",
				"registrace",
				"zapomenute-heslo",
			],
			"Admin" => [
				"admin",
			],
		];

		foreach ($controllers as $key => $c) {
			$result = array_search($route, $c, true);
			if ($result !== false) {
				return new $key;
			}
		}

		return new Index();
	}
}