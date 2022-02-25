<?php

class Database
{
	private $mysql;
	private $rows;

	public function __construct($host, $user, $pass, $db) {
		$this->mysql = new mysqli($host, $user, $pass, $db);

		if ($this->mysql->connect_errno) {
			die("DB Connection failed");
		}
		$this->mysql->query("SET NAMES 'utf8'");
		$this->mysql->set_charset("utf8");
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	}

	public function query($query) {
		return $this->mysql->query($query);
	}

	public function read($query, $format = NULL, ...$param) {
		$data = array();

		if($format == NULL) {
			$result = $this->mysql->query($query);
			$this->num_rows = $result->num_rows;

			$i = 0;
			while ($row = $result->fetch_assoc()) {
				$data[] = $row;
				++$i;
			}

			if ($i == 1 && strpos(substr($query, strlen($query) - strlen("LIMIT 1") - 1), "LIMIT 1") !== false) {
				$data = $data[0];
			}

			$result->free();
		} else {
			try {
				$stmt = $this->mysql->prepare($query);
				if ($stmt === false) {
					throw new Exception($this->mysql->error);
				}
				$stmt->bind_param($format, ...$param);
				$stmt->execute();
			} catch (Exception $e) {
				die($e->getMessage());
			}

			$result = $stmt->get_result();
			$this->rows = $result->num_rows;
			$meta = $stmt->result_metadata();
			$columns = $meta->fetch_fields();

			$i = 0;
			while ($row[$i] = $result->fetch_array()) {
				$data[$i] = array();
				foreach($columns as $col) {
					$data[$i][$col->name] = $row[$i][$col->name];
				}
				++$i;
			}

			if ($i == 1 && strpos(substr($query, strlen($query) - strlen("LIMIT 1") - 1), "LIMIT 1") !== false) {
				$data = $data[0];
			}
		}

		return $data;
	}

	public function write($query, $format, ...$param) {
		try {
			$stmt = $this->mysql->prepare($query);
			$stmt->bind_param($format, ...$param);
			if ($stmt === false) {
				throw new Exception($this->mysql->error);
			}
			$this->rows = 0;
			if($stmt->execute()) {
				$this->rows = $stmt->affected_rows;
			}
			$stmt->close();
		} catch(Exception $e) {
			die($e->getMessage());
		}

		return $this->rows;
	}

	public function lastRows() {
		return $this->rows;
	}

	public function getLastID() {
		$result = $this->query("SELECT LAST_INSERT_ID() AS id");
		$id = $result->fetch_assoc()["id"];
		return (empty($id) ? 0 : $id);
	}

	public function getError() {
		return $this->mysql->error;
	}

	public function __destruct() {
		$this->mysql->close();
	}
}