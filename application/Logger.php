<?php

class Logger {

	public $logs = array();
	public $errors = array();
	public $warnings = array();

	function __construct($encoded = null) {
		if (!empty($encoded)) {
			if (isset($encoded->success))
				$this->logs = $encoded->success;
			if (isset($encoded->errors))
				$this->errors = $encoded->errors;
			if (isset($encoded->warnings))
				$this->warnings = $encoded->warnings;
		}
	}

	function success($message) {
		array_push($this->logs, $message);
	}

	function error($message) {
		array_push($this->errors, $message);
	}

	function warning($message) {
		array_push($this->warnings, $message);
	}

	function getErrorCount() {
		return count($this->errors);
	}

	function firstSuccess() {
		if (isset($this->logs[0]))
			return $this->log[0];

		return null;
	}

	function firstError() {
		if (isset($this->errors[0]))
			return $this->errors[0];

		return null;
	}
	function firstWarning() {
		if (isset($this->warnings[0]))
			return $this->warnings[0];

		return null;
	}

}