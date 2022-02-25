<?php

class Index extends RCViewController {

	function __construct() {

		global $config;

		parent::__construct();

		$this->paths = [
			"index" => "Index",

			"kontakt" => "Kontakt",
			"silna-hesla" => "Index",

			"archiv" => "Archiv",
			"cestovky" => "Cestovky",
			"diskuze" => "Diskuze",
			"recenze" => "Recenze",
		];

		$articles = $this->get("articles");
		if (!empty($_GET["route"]) && array_search($_GET["route"], $this->paths) === false) {

			if ($_GET["route"] == "novy-clanek" && $this->isUserAdmin()) {
				$this->Article(new stdClass());
				return;
			}
			foreach ($articles as $a) {
				if ($a->url == $_GET["route"]) {
					$this->Article($a);
					return;
				}
			}
		}
	
		$this->Route();
	}

	function Index() { }

	function Kontakt() {

		global $config;

		if (isset($_POST["submit"])) {

			$log = new Logger();

			if (!isset($_SESSION["contact_email"]))
				$_SESSION["contact_email"] = false;

			if ($_SESSION["contact_email"] == true) {
				$log->error("Děláte to moc často");
				$this->set("output", $log);
				return;
			}

			$rules = [
				[
					"type" => "email",
					"field" => "email",
					"pretty" => "E-mailová adresa",
					"required" => true,
					"minLength" => 8,
					"maxLength" => 30,
				],
				[
					"type" => "string",
					"field" => "subject",
					"pretty" => "Předmět zprávy",
					"required" => true,
					"minLength" => 3,
					"maxLength" => 30,
				],
				[
					"type" => "string",
					"field" => "content",
					"pretty" => "Obsah zprávy",
					"required" => true,
					"minLength" => 10,
				],
			];

			$validator = new Validator(new Request($_POST), $log);
			if (!$validator->validateCaptcha()) {
				$log->error("Určite nejste robot?");
			} else if ($validator->validate($rules)) {
				$_SESSION["contact_email"] = true;
				$log->success("Zpráva byla odeslána");

				$email = new EmailService();
				$email->SendMessage($config["contactEmail"], "Kontaktní formulář: " . $_POST["subject"], $_POST["content"], $_POST["email"]);
			}
			$this->set("output", $log);
		}
	}

	function Diskuze() {

		global $config;

		if (!isset($_GET["ck"])) {
			header("Location: " . $config["baseUrl"] . "/cestovky");
		}

		// get CK basic data
		$request = [
			"ck" => $_GET["ck"],
		];

		$ck = $this->request("/api/cestovky.php?route=get", $request, "GET");
		$this->set("ck_data_basic", $ck);
		$this->set("titleText", $ck->nazev . " | Diskuze");

		if (empty($ck->id)) {
			header("Location: " . $config["baseUrl"] . "/cestovky");
		}

		if (!isset($_REQUEST["action"]))
			$_REQUEST["action"] = "";

		if ($_REQUEST["action"] == "add") {
			if (!$this->isUserLogged()) {
				if (!Validator::validateCaptcha()) {
					$log = new Logger();
					$log->error("Určitě nejste robot?");
					$this->set("output", $log);
					return;
				}
			}
			$userID = 0;
			if ($this->isUserLogged())
				$userID = $_SESSION["id"];

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
				"ip" => $_SERVER["REMOTE_ADDR"],
				"user_id" => $userID,
				"ck_id" => $ck->id,
			]);

			$result = $this->request("/api/diskuze.php?route=add", $request, "POST");
			$this->set("output", $result);

			if (!empty($result->success)) {
				$onlyAdmins = !$this->isUserLogged() || !$this->isUserActivated();
				$this->sendNotifications($ck, $onlyAdmins, true);
			}

			$this->updateLatest();
		} else if ($_REQUEST["action"] == "edit") {
			if (!$this->isUserLogged())
				return;

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
				"user_id" => $_SESSION["id"],
				"admin_view" => $this->isUserAdmin(),
			]);
			$result = $this->request("/api/diskuze.php?route=edit", $request, "POST");
			$this->set("output", $result);
		} else if ($_REQUEST["action"] == "delete") {
			if (!$this->isUserLogged())
				return;

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
				"user_id" => $_SESSION["id"],
				"admin_view" => $this->isUserAdmin(),
			]);
			$result = $this->request("/api/diskuze.php?route=delete", $request, "POST");
			$this->set("output", $result);
			$this->updateLatest();
		}
	}

	function Article($article = null) {

		global $config;

		if (!isset($_REQUEST["action"]))
			$_REQUEST["action"] = "";

		if (!isset($article->id)) {
			if ($this->isUserAdmin() && $_REQUEST["action"] == "add") {
				$request = [
					"api_key" => $config["privateApiKey"],
					"content" => $_REQUEST["content"],
					"article_title" => $_REQUEST["article_title"],
					"article_title_full" => $_REQUEST["article_title_full"],
				];

				$result = $this->request("/api/article.php?route=add", $request, "POST");
				$this->set("output", $result);
			}

			return;
		}

		else if ($this->isUserAdmin() && $_REQUEST["action"] == "edit") {
			$request = [
				"api_key" => $config["privateApiKey"],
				"id" => $article->id,
				"content" => $_REQUEST["content"],
				"article_title" => $_REQUEST["article_title"],
				"article_title_full" => $_REQUEST["article_title_full"],
			];

			$result = $this->request("/api/article.php?route=edit", $request, "POST");
			
			$newPath = Helpers::getValidPath($_REQUEST["article_title"]);
			$newUrl = $config["baseUrl"] . "/" . $newPath;
			if (isset($result->success) && $newPath != $article->url) {
				$result->warnings = array();
				array_push($result->warnings, "Odkaz na tenhle článek se změnil");
				array_push($result->warnings, "Nový odkaz: <a href=\"".$newUrl."\">".$newUrl."</a>");
			}

			$this->set("output", $result);
		}

		$request = [
			"id" => $article->id,
		];

		$article = $this->request("/api/article.php?route=get", $request, "GET");
		$this->set("article", $article);
		$this->set("titleText", $article->title);
	}
	
	function Archiv() {

		global $config;

		$result = $this->request("/api/recenze.php?route=archiv", [], "GET");
		$this->set("cestovky", $result);
		$this->set("titleText", "Archiv");

		if (isset($_GET["detail"])) {
			$exists = false;
			foreach ($result->cestovky as $c) {
				if ($c->nazev == urldecode($_GET["detail"])) {
					$exists = true;
					break;
				}
			}
			// ck neexistuje
			if (!$exists) {
				header("Location: " . $config["baseUrl"] . "/archiv");
				return;
			}

			$request = [
				"detail" => $_GET["detail"],
			];

			$result = $this->request("/api/recenze.php?route=archiv", $request, "GET");

			$this->set("recenze", $result);
			$this->set("titleText", urldecode($_GET["detail"]) . " | Archiv");
		}
	}

	function Recenze() {

		global $config;

		if (!isset($_GET["ck"])) {
			header("Location: " . $config["baseUrl"] . "/cestovky");
		}

		// get CK basic data
		$request = [
			"ck" => $_GET["ck"],
		];

		$ck = $this->request("/api/cestovky.php?route=get", $request, "GET");
		$this->set("ck_data_basic", $ck);
		$this->set("titleText", $ck->nazev);

		// CK doesn't exist, redirect
		if (isset($ck->errors)) {
			header("Location: " . $config["baseUrl"] . "/cestovky");
		}

		$session = new Data($_SESSION);

		if (!isset($_REQUEST["action"]))
			$_REQUEST["action"] = "";

		// handle actions
		switch ($_REQUEST["action"]) {

			case "subscribe":
			case "unsubscribe":

				if (!$this->isUserLogged() || $ck->recenze_povoleny == false) {
					break;
				}

				$request = [
					"api_key" => $config["privateApiKey"],
					"action" => $_POST["action"],
					"ck_id" => $ck->id,
					"user_id" => $_SESSION["id"],
					"user_activated" => $this->isUserActivated(),
				];

				// prevent subscribe spam
				if ($request["action"] == "subscribe") {
					if (!isset($_SESSION["lastSubscribedID"]) || $_SESSION["lastSubscribedID"] != $ck->id) {
						$_SESSION["lastSubscribedID"] = $ck->id;
					} else {
						$log = new logger();
						$log->error("Děláte to moc často");
						$this->data->set("output", $log);
						break;
					}
				}

				// send the request
				$result = $this->request("/api/user.php?route=subscribe", $request, "POST");
				$this->set("output", $result);
				$this->set("user_subscribed", false);
				break;

			case "add_recenze":
			case "edit_recenze":

				if (!$this->isUserLogged()) {
					if (!Validator::validateCaptcha()) {
						$log = new Logger();
						$log->error("Určitě nejste robot?");
						$this->set("output", $log);
						break;
					}
				}

				global $config;

				// try to upload files first to get file names
				$attachments = new Attachments();
				$uploadLog = $attachments->upload();

				// error uploading files, send to output and delete any uploaded files
				if (!empty($uploadLog->errors)) 
				{
					$this->set("output", $uploadLog);
					$attachments->delete();
				}
				// no error or no files uploaded
				else
				{
					$userID = 0;
					if ($this->isUserLogged())
						$userID = $session->get("id");

					$request = array_merge($_POST, [
						"api_key" => $config["privateApiKey"],
						"ck_id" => $ck->id,
						"user_id" => $userID,
						"user_activated" => $session->get("ucet_aktivovan"),
						"ip" => $_SERVER["REMOTE_ADDR"],
						"action" => $_REQUEST["action"],
						"admin_view" => $this->isUserAdmin(),
					]);

					$result = $this->request("/api/recenze.php?route=create", $request, "POST");
					$this->set("output", $result);

					// success
					if (isset($result->recenze_id) && $result->recenze_id != 0)
					{
						$files = $attachments->get();
						if (!empty($files[0]) || !empty($files[1]))
						{
							$request = array_merge($_POST, [
								"api_key" => $config["privateApiKey"],
								"recenze_id" => $result->recenze_id,
								"images" => $files,
							]);

							$this->request("/api/recenze.php?route=upload", $request, "POST");
						}

						// get latest after add
						$result = $this->request("/api/recenze.php?route=getLatest", [], "GET");
						$this->set("latest", $result);

						$onlyAdmins = !$this->isUserLogged() || !$this->isUserActivated();
						$this->sendNotifications($ck, $onlyAdmins);
					}
					// errors with the input, delete any uploaded files
					else
					{
						$attachments->delete();
					}
				}

				break;

			case "delete_recenze":

				if (!$this->isUserLogged()) {
					break;
				}

				$request = [
					"api_key" => $config["privateApiKey"],
					"user_id" => $session->get("id"),
					"recenze_id" => (int) $_POST["recenze_id"],
					"admin_view" => isUserAdmin(),
				];
				$result = $this->request("/api/recenze.php?route=delete", $request, "GET");

				$this->set("output", $result);

				break;

			case "add_reakce":

				if (!$this->isUserLogged())
					break;

				$request = [
					"api_key" => $config["privateApiKey"],
					"recenze_id" => $_POST["recenze_id"],
					"user_id" => $session->get("id"),
					"user_activated" => $session->get("ucet_aktivovan"),
					"recenze_povoleny" => $ck->recenze_povoleny,
					"ip" => $_SERVER["REMOTE_ADDR"],
					"content" => $_POST["content"],
				];

				$result = $this->request("/api/reakce.php?route=add", $request, "POST");
				$this->set("output", $result);

				break;

			case "delete_reakce":

				if (!$this->isUserLogged())
					break;

				$request = [
					"user_id" => $session->get("id"),
					"reakce_id" => (int) $_POST["reakce_id"],
					"admin_view" => isUserAdmin(),
					"api_key" => $config["privateApiKey"],
				];

				$result = $this->request("/api/reakce.php?route=delete", $request, "POST");

				$this->set("output", $result);
				break;

		}

		if ($this->isUserLogged()) {
			
			// get subscribed status BEFORE update
			$request = [
				"api_key" => $config["privateApiKey"],
				"user_id" => $_SESSION["id"],
				"ck_id" => $ck->id,
			];
			$result = $this->request("/api/user.php?route=subscribe&action=get", $request, "GET");

			$this->set("user_subscribed", $result->subscribed);
		}

		if ($this->isUserAdmin()) {
			$_GET["admin_view"] = 1;
		}

		if (!isset($_GET["page"]))
			$_GET["page"] = 0;
		$request = [
			"api_key" => $config["privateApiKey"],
			"admin_view" => $this->isUserAdmin(),
			"ck_id" => $ck->id,
			"page" => $_GET["page"],
		];
		$ck_data = $this->request("/api/cestovky.php?route=recenze", $request, "GET");
		$this->set("ck_data", $ck_data);
	}

	function Cestovky() {

		if (!isset($_GET["hledej"]))
			$_GET["hledej"] = "";

		$request = [
			"ck" => $_GET["hledej"],
		];

		//$cestovky = $this->request("/search_callback.php?", $request, "GET");
		$cestovky = $this->request("/api/cestovky.php?route=get", [], "GET");
		$this->set("cestovkySearch", $cestovky);
		$this->set("titleText", "Seznam cestovek");
	}
}