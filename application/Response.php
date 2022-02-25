<?php

define("RESPONSE_INCLUDE_LOG", true);

class Response extends Data {

	private $log;

	function __construct() {
		parent::__construct();
		$this->log = new Logger();
	}

	function success($includeLog = false) {

		if ($includeLog)
			$this->saveLogger();

		return json_encode($this->data);
	}

	private function saveLogger() {
		if (!empty($this->log->logs))
			$this->set("success", $this->log->logs);
		if (!empty($this->log->warnings))
			$this->set("warnings", $this->log->warnings);
		if (!empty($this->log->errors))
			$this->set("errors", $this->log->errors);
	}

	function getLogger() {
		return $this->log;
	}

	function unauthorized() {
		return $this->invalidRequest("Neautorizovaná požadavka");
	}

	function invalidRequest($message = "") {
		$this->log->error(empty($message) ? "Neplatná požadavka" : $message);
		$this->saveLogger();
		return json_encode($this->data);
	}

	function notFound($message = "") {
		return (empty($message) ? "HTTP/1.1 404 Not Found" : $message);
	}

	function error($message = null) {
		if (!empty($message)) {
			$this->log->error($message);
		}

		//$this->flush();
		$this->saveLogger();
		return json_encode($this->log);
	}
}