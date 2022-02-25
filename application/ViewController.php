<?php

class ViewController extends Controller {

	protected $data;

	function Route($path = null) {
		global $config;

		if ($path != null) {
			echo call_user_func(array($this, $path));
			return;
		}

		$path = $_GET["route"];
		$method = "GET";

		if (isset($_SERVER["REQUEST_METHOD"])) {
			$method = $_SERVER["REQUEST_METHOD"];
		}

		if (empty($path) || !array_key_exists($path, $this->paths)) {
		    header("Location: " . $config["baseUrl"]);
		}

		if (is_string($this->paths[$path])) {
			echo call_user_func(array($this, $this->paths[$path]));
			return;
		}
		
		echo call_user_func(array($this, $this->paths[$path][$method]));
	}

	public function loadTemplate($template, $absolutePath = false) {
		global $config;

		global $view;

		global $lang;

		if ($absolutePath == false)
			include ("./views/" . $template);
		else
			include ($template);
	}

	public function set($key, $value) {
		$this->data->set($key, $value);
	}

	public function get($key) {
		return $this->data->get($key);
	}

}