<?php

class ReakceApiController extends ApiController {

	private $db;

	function __construct() {

		global $config;

		parent::__construct();

		$this->RegisterRoute("/api/reakce.php", "add", "POST");
		$this->RegisterRoute("/api/reakce.php", "delete", "POST");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function add() {

		global $config;

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$userID = $request->get("user_id");
		$recenzeID = $request->get("recenze_id");
		$userActivated = $request->get("user_activated");
		$userIP = $request->get("ip");
		$content = strip_tags(htmlspecialchars($request->get("content")));
		$recenzePovoleny = $request->get("recenze_povoleny");

		if ($recenzePovoleny != true || $userID === null || ($recenzeID === null || empty($recenzeID)) || empty($userIP))
			return $response->invalidRequest();

		if ($userID == 0)
			return $response->invalidRequest("Pro přidání reakce se musíte přihlásit");

		if (!isset($userActivated) || $userActivated == 0)
			return $response->invalidRequest("Pro přidání reakce musíte aktivovat svůj účet");

		// Validate input

		$rules = [
			[
				"type" => "string",
				"field" => "content",
				"pretty" => "Text reakce",
				"required" => true,
				"minLength" => 10,
				"maxLength" => 500,
			],
		];

		$validator = new Validator($request, $log);
		if (!$validator->validate($rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$status = ContentStatus::OK;

		// Recenze neexistuje

		$this->db->read("SELECT id FROM rc_recenze WHERE id = ? AND stav_id = ? LIMIT 1", "ii", $recenzeID, $status);

		if ($this->db->lastRows() == 0)
			return $response->invalidRequest("Recenze neexistuje");

		// Banlist

		$this->db->read("SELECT ip FROM rc_banlist WHERE ip = ? LIMIT 1", "s", $userIP);

		if ($this->db->lastRows() != 0)
			return $response->invalidRequest("Litujeme, ale Vaše IP adresa byla zabanována. Pokud si myslíte, že jde o omyl, neváhejte a kontaktujte nás <a href='" . $config["baseUrl"] . "/kontakt'>zde</a>");

		// Recenze timeout

		$this->db->read("SELECT id FROM rc_komentare WHERE ip = ? AND add_date BETWEEN DATE_SUB(NOW() , INTERVAL {$config['reviewPostTimeout']} MINUTE) AND NOW()", "s", $userIP);

		if ($this->db->lastRows() != 0)
			return $response->invalidRequest("Děláte to moc často");

		$this->db->write("
			INSERT INTO rc_komentare
				(recenze_id, user_id, content, ip, stav_id)
			VALUES
				(?, ?, ?, ?, ?)"
			, "iissi", $recenzeID, $userID, $content, $userIP, $status
		);

		if ($this->db->lastRows() != 0) {
			$result = $this->db->read("SELECT LAST_INSERT_ID() AS id");
			$reakceID = $result[0]["id"];
			$response->set("reakce_id", $reakceID);
			$log->success("Reakce přidána!");
		} else {
			$log->error("Recenzi se nepodařilo přidat");
		}

		return $response->success(true);
	}

	function delete() {

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$reakceID = $request->get("reakce_id");
		$userID = $request->get("user_id");

		if (empty($reakceID) || empty($userID))
			return $response->invalidRequest();

		$admin = $request->get("admin_view");

		$status = ContentStatus::OK;
		$newStatus = ContentStatus::Deleted;

		$this->db->write("UPDATE rc_komentare SET stav_id = ? WHERE id = ? AND stav_id = ? AND (user_id = ? OR 1 = ?)", "iiiii", $newStatus, $reakceID, $status, $userID, $admin);

		if ($this->db->lastRows() == 0) {
			return $response->invalidRequest("Není co smazat");
		}

		$adminText = ($admin == true ? "Admin: " : "");
		$response->getLogger()->success($adminText . " Reakce smazána");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}
}