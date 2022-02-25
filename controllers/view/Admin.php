<?php

class Admin extends RCViewController {
	
	private $subviews;

	function __construct() {

		global $config;

		parent::__construct();

		if (empty($_GET["subview"]))
			$_GET["subview"] = "cestovky";

		if (!$this->isUserAdmin()) {
			header("Location: " . $config["baseUrl"]);
		} else if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/views/admin/" . $_GET["subview"] . ".phtml")) {
			header("Location: /admin/cestovky");
		}

		$request = [
			"api_key" => $config["privateApiKey"],
		];

		$result = $this->request("/api/admin.php?route=getHeader", $request, "GET");

		$this->set("admin_header", $result);
		$this->set("titleText", "Admin Panel");

		$this->Route(ucfirst($_GET["subview"]));
	}

	function Diskuze() {

		global $config;

		if (!empty($_REQUEST["action"])) {

			if ($_REQUEST["action"] == "confirm") {
				$request = [
					"api_key" => $config["privateApiKey"],
					"content_id" => $_REQUEST["prispevek_id"],
					"status" => ContentStatus::OK,
				];

				$result = $this->request("/api/admin.php?route=setContentStatus", $request, "POST");
				$this->set("output", $result);
				$this->updateLatest();
			} else if ($_REQUEST["action"] == "delete_prispevek_permanent") {
				$request = [
					"api_key" => $config["privateApiKey"],
					"diskuze_id" => $_REQUEST["prispevek_id"],
				];

				$result = $this->request("/api/diskuze.php?route=deletePermanent", $request, "POST");

				$this->set("output", $result);

				$this->updateLatest();
			}
		}

		if (!isset($_POST["status"]))
			$_POST["status"] = ContentStatus::Deleted;

		$request = array_merge($_POST, [
			"api_key" => $config["privateApiKey"],
		]);

		$result = $this->request("/api/admin.php?route=getPrispevky", $request, "GET");

		$this->set("prispevky", $result);
	}

	function Recenze() {

		global $config;

		if (!empty($_REQUEST["action"])) {

			if ($_REQUEST["action"] != "delete_recenze_permanent") {
				
				$status = null;

				switch ($_REQUEST["action"]) {
					case "confirm":
						$status = ContentStatus::OK;
						break;
					
					case "delete":
						$status = ContentStatus::Deleted;
						break;
				}

				if ($status !== null) {
					$request = [
						"api_key" => $config["privateApiKey"],
						"content_id" => $_REQUEST["recenze_id"],
						"status" => $status,
					];

					$result = $this->request("/api/admin.php?route=setContentStatus", $request, "POST");
					$this->set("output", $result);
					$this->updateLatest();
				}
			} else {
				$request = [
					"api_key" => $config["privateApiKey"],
					"recenze_id" => $_REQUEST["recenze_id"],
				];

				$result = $this->request("/api/recenze.php?route=deletePermanent", $request, "POST");
				
				if (!empty($result->prilohy_unlink)) {
					foreach ($result->prilohy_unlink as $p) {
						if (!empty($p->url)) {
							unlink($p->url);
						}
					}
				}

				$this->set("output", $result);

				$this->updateLatest();
			}
		}

		if (!isset($_POST["status"]))
			$_POST["status"] = ContentStatus::Confirm;

		$request = array_merge($_POST, [
			"api_key" => $config["privateApiKey"],
		]);

		$result = $this->request("/api/admin.php?route=getRecenze", $request, "GET");

		$this->set("recenze", $result);

	}

	function Top100() {

		global $config;

		$request = [
			"api_key" => $config["privateApiKey"],
		];

		$result = $this->request("/api/admin.php?route=top100", $request, "GET");

		$this->set("top100", $result);
	}

	function Lookup() {

		global $config;

		$request = [
			"api_key" => $config["privateApiKey"],
		];

		if (!empty($_GET["ip"])) {

			$request["ip"] = $_GET["ip"];

			$result = $this->request("/api/admin.php?route=lookup", $request, "GET");

			$this->set("lookup_detail", $result);
		}

		$result = $this->request("/api/admin.php?route=lookup", $request, "GET");

		$this->set("lookup", $result);
	}

	function Articles() {

		global $config;
		if (!isset($_REQUEST["action"]))
			$_REQUEST["action"] = "";

		if ($_REQUEST["action"] == "delete") {

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
			]);
			$result = $this->request("/api/article.php?route=delete", $request, "POST");
			$this->set("output", $result);
			$this->updateArticleList();
		}
	}

	function Users() {

		global $config;

		$result = $this->request("/api/admin.php?route=getUsers", ["api_key" => $config["privateApiKey"]], "POST");

		$this->set("users", $result);
	}

	function Cestovky() {

		global $config;

		if (!isset($_REQUEST["action"]))
			$_REQUEST["action"] = "";

		switch ($_REQUEST["action"]) {

			case "add_cestovka":

				$request = [
					"api_key" => $config["privateApiKey"],	
					"nazev" => $_POST["nazev"],
				];

				$result = $this->request("/api/cestovky.php?route=add", $request, "POST");
				$this->set("output", $result);

				break;

			case "delete_cestovka":

				$request = [
					"api_key" => $config["privateApiKey"],	
					"ck_id" => $_POST["ck_id"],
				];

				$result = $this->request("/api/cestovky.php?route=delete", $request, "POST");

				if (!empty($result->prilohy_unlink)) {
					foreach ($result->prilohy_unlink as $p) {
						if (!empty($p->url)) {
							unlink($p->url);
						}
					}
				}

				$this->set("output", $result);

				break;
		}



		$result = $this->request("/api/cestovky.php?route=get", [], "GET");

		$this->set("cestovky", $result);
	}

	function Banlist() {

		global $config;

		$session = new Data($_SESSION);

		if (isset($_POST["do_ban"]) || isset($_POST["do_unban"])) {

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
				"admin_id" => $session->get("id"),
			]);

			if (isset($_POST["do_ban"]))
				$result = $this->request("/api/admin.php?route=ban", $request, "POST");
			else
				$result = $this->request("/api/admin.php?route=unban", $request, "POST");

			$this->set("output", $result);
		}

		$result = $this->request("/api/admin.php?route=getBanlist", ["api_key" => $config["privateApiKey"]], "POST");

		$this->set("banlist", $result);
	}
}