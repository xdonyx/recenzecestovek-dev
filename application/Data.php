<?php

class Data {

	public $data;

	function __construct($data = null) {

		if($data != null) {
			$this->data = $data;
		} else {
			$this->data = Array();
		}
	}

	public function get($key) {

		if (!isset($this->data[$key])) {
			return null;
		}

		return $this->data[$key];
	}

	public function flush() {
		$this->data = Array();
	}

	public function set($key, $value) {
		$this->data[$key] = $value;
	}

	public function clear($key) {
		unset($this->data[$key]);
	}
}