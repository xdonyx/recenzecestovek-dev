<?php

class DiskuzeApiController extends ApiController {
	
	private $db;

	private $rules = [
		[
			"type" => "string",
			"field" => "article_title",
			"pretty" => "Nadpis",
			"required" => true,
			"minLength" => 6,
		],
		[
			"type" => "string",
			"field" => "article_title_full",
			"pretty" => "Celý nadpis",
			"minLength" => 6,
		],
		[
			"type" => "string",
			"field" => "content",
			"pretty" => "Obsah článku",
			"required" => true,
			"minLength" => 10,
		],
	];

	function __construct() {
		global $config;
		parent::__construct();

		$this->RegisterRoute("/api/diskuze.php", "get", "GET");
		$this->RegisterRoute("/api/diskuze.php", "add", "POST");
		$this->RegisterRoute("/api/diskuze.php", "delete", "POST");
		$this->RegisterRoute("/api/diskuze.php", "deletePermanent", "POST");

		$this->RegisterRoute("/api/article.php", "edit", "POST");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function get() {

		global $config;

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$ckID = $request->get("ck_id");
		$page = $request->get("page");
		$admin = $request->get("admin");

		if (empty($ckID))
			return $response->invalidRequest();

		if (!is_numeric($page) || empty($page)) {
			$page = 1;
		}

		$perPage = 1;
		$offset = ($page - 1) * $perPage;
		$result = $this->db->read("
			SELECT
				f.*, u.display_name
			FROM
				rc_forum f
			LEFT JOIN rc_uzivatel u ON f.user_id = u.id
			WHERE f.ck_id = ? AND f.stav_id = ?
			ORDER BY f.add_date
			DESC LIMIT ?, ?", "iiii", $ckID, ContentStatus::OK, $offset, $perPage);

		return json_encode($result);
	}

	function add() {

		global $config;

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$ckID = $request->get("ck_id");
		$userID = $request->get("user_id");
		$userIP = $request->get("ip");
		$content = $request->get("content");
		$guestName = $request->get("guest_name");
		$guestEmail = $request->get("guest_email");

		if (empty($ckID) || empty($userIP) || is_null($userID)) {
			return $response->invalidRequest();
		}

		$rules = [
			[
				"type" => "string",
				"field" => "content",
				"pretty" => "Příspěvek",
				"required" => true,
				"minLength" => 15,
				"maxLength" => 1000,
			],
		];

		if ($userID == 0) {
			$guestRules = [
				[
					"type" => "string",
					"field" => "guest_name",
					"pretty" => "Jméno",
					"required" => true,
					"minLength" => 6,
					"maxLength" => 30,
				],
				[
					"type" => "string",
					"field" => "guest_email",
					"pretty" => "E-mailová adresa",
					"required" => true,
					"minLength" => 6,
					"maxLength" => 30,
				],
			];
			$rules = array_merge($guestRules, $rules);
		}

		$validator = new Validator($request, $response->getLogger());
		if (!$validator->validate($rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		// Banlist

		$this->db->read("SELECT ip FROM rc_banlist WHERE ip = ? LIMIT 1", "s", $userIP);

		if ($this->db->lastRows() != 0)
			return $response->invalidRequest("Litujeme, ale Vaše IP adresa byla zabanována. Pokud si myslíte, že jde o omyl, neváhejte a kontaktujte nás <a href='" . $config["baseUrl"] . "/kontakt'>zde</a>");

		// CK neexistuje

		$result = $this->db->read("SELECT prispevky_povoleny FROM rc_cestovky WHERE id = ? LIMIT 1", "i", $ckID);

		if ($this->db->lastRows() == 0)
			return $response->invalidRequest("Cestovka neexistuje");

		// CK aktivni

		if ($result["prispevky_povoleny"] == false)
			return $response->invalidRequest("Litujeme, ale tahle cestovka není aktivní");

		// Recenze timeout

		$this->db->read("SELECT id FROM rc_forum WHERE ip = ? AND add_date BETWEEN DATE_SUB(NOW() , INTERVAL {$config['reviewPostTimeout']} MINUTE) AND NOW()", "s", $userIP);

		if ($this->db->lastRows() != 0)
			return $response->invalidRequest("Děláte to moc často");

		$result = $this->db->write("
			INSERT INTO rc_forum
				(ck_id, user_id, content, ip, stav_id, guest_name, guest_email)
			VALUES
				(?, ?, ?, ?, ?, ?, ?)", "iississ", $ckID, $userID, $content, $userIP, ContentStatus::OK, $guestName, $guestEmail);
		
		if ($result > 0) {
			$response->getLogger()->success("Příspěvek přidán!");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function delete() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$prispevekID = $request->get("prispevek_id");
		$userID = $request->get("user_id");
		$admin = $request->get("admin_view");

		if (empty($prispevekID) || empty($userID))
			return $response->invalidRequest();

		$result = $this->db->write("UPDATE rc_forum SET stav_id = ? WHERE id = ? AND stav_id = ? AND (user_id = ? OR 1 = ?) LIMIT 1", "iiiii", ContentStatus::Deleted, $prispevekID, ContentStatus::OK, $userID, $admin);
		
		if ($result > 0) {
			$response->getLogger()->warning("Příspěvek smazán");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function deletePermanent() {

		$request = $this->request;

		$response = $this->response;
		$log = $response->getLogger();

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$diskuzeID = $request->get("diskuze_id");

		if (empty($diskuzeID))
			return $response->invalidRequest();

		$recenze = $this->db->read("SELECT id FROM rc_forum WHERE id = ? LIMIT 1", "i", $diskuzeID);

		if ($this->db->lastRows() == 0)
			return $response->invalidRequest("Příspěvek neexistuje");

		$this->db->write("DELETE FROM rc_forum WHERE id = ? LIMIT 1", "i", $diskuzeID);

		$log->success("Příspěvek byl navždy smazán!");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function edit() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$prispevekID = $request->get("prispevek_id");
		$userID = $request->get("user_id");
		$admin = $request->get("admin_view");
		$content = $request->get("content");

		if (empty($prispevekID) || empty($userID))
			return $response->invalidRequest();

		$rules = [
			[
				"type" => "string",
				"field" => "content",
				"pretty" => "Příspěvek",
				"required" => true,
				"minLength" => 15,
				"maxLength" => 1000,
			],
		];

		$validator = new Validator($request, $response->getLogger());
		if (!$validator->validate($rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$result = $this->db->write("UPDATE rc_forum SET content = ?, edit_date = CURRENT_TIMESTAMP WHERE id = ? AND ((stav_id <> ? AND user_id = ?) OR 1 = ?) LIMIT 1", "siiii", $content, $prispevekID, ContentStatus::Deleted, $userID, $admin);
		
		if ($result > 0) {
			$response->getLogger()->success("Změny uloženy");
		} else {
			$response->getLogger()->warning("Žádné změny");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}
}