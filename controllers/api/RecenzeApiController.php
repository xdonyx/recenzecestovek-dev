<?php

class RecenzeApiController extends ApiController {

	private $db;

	function __construct() {

		global $config;

		parent::__construct();

		$this->RegisterRoute("/api/recenze.php", "create", "POST");
		$this->RegisterRoute("/api/recenze.php", "delete", "GET");
		$this->RegisterRoute("/api/recenze.php", "deletePermanent", "POST");
		$this->RegisterRoute("/api/recenze.php", "upload", "POST");
		$this->RegisterRoute("/api/recenze.php", "archiv", "GET");

		$this->RegisterRoute("/api/recenze.php", "getLatest", "GET");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function archiv() {

		$request = $this->request;

		$response = $this->response;

		$ckParam = urldecode($request->get("detail"));

		if (!empty($ckParam)) {

			$result = $this->db->read("
				SELECT comment_Cestovka, comment_content, comment_author, comment_date
				FROM rc_archiv
				WHERE comment_Cestovka = ?
				ORDER BY comment_date DESC"
			, "s", $ckParam);

			return json_encode($result);
		}
		$result = $this->db->read("SELECT MAX(comment_date) AS latest, MIN(comment_date) AS oldest FROM rc_archiv LIMIT 1");
		$response->set("latest", $result["latest"]);
		$response->set("oldest", $result["oldest"]);

		$result = $this->db->read("SELECT a.comment_Cestovka AS nazev FROM rc_archiv a LEFT JOIN rc_cestovky c ON c.nazev = a.comment_Cestovka WHERE c.archiv_viditelny = 1 GROUP BY a.comment_Cestovka");
		$response->set("cestovky", $result);

		return $response->success();
	}

	function deletePermanent() {

		$request = $this->request;

		$response = $this->response;
		$log = $response->getLogger();

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$recenzeID = $request->get("recenze_id");

		if (empty($recenzeID))
			return $response->invalidRequest();

		$recenze = $this->db->read("SELECT id FROM rc_recenze WHERE id = ? LIMIT 1", "i", $recenzeID);

		if ($this->db->lastRows() == 0)
			return $response->invalidRequest("Recenze neexistuje");
		
		$prilohy = $this->db->read("SELECT url FROM rc_recenze_obrazky WHERE recenze_id = ?", "i", $recenzeID);
		$response->set("prilohy_unlink", $prilohy);

		$this->db->write("DELETE FROM rc_recenze_obrazky WHERE recenze_id = ?", "i", $recenzeID);
		$this->db->write("DELETE FROM rc_komentare WHERE recenze_id = ?", "i", $recenzeID);
		$this->db->write("DELETE FROM rc_recenze WHERE id = ? LIMIT 1", "i", $recenzeID);

		$log->success("Recenze byla navždy smazána!");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function delete() {

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$recenzeID = $request->get("recenze_id");
		$userID = $request->get("user_id");
		$admin = $request->get("admin_view");

		if (empty($recenzeID) || empty($userID))
			return $response->invalidRequest();

		$this->db->write("UPDATE rc_recenze SET stav_id = ? WHERE id = ? AND stav_id = ? AND (user_id = ? OR 1 = ?)", "iiiii", ContentStatus::Deleted, $recenzeID, ContentStatus::OK, $userID, $admin);

		if ($this->db->lastRows() == 0) {
			return $response->invalidRequest("Není co smazat");
		}

		$adminText = ($admin == true ? "Admin: " : "");
		$response->getLogger()->success($adminText . " Recenze smazána");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function upload() {

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$recenzeID = $request->get("recenze_id");
		$images = $request->get("images");

		if (!empty($images)) {
			$this->db->write("DELETE FROM rc_recenze_obrazky WHERE recenze_id = ?", "i", $recenzeID);
		}

		foreach ($images as $image) {
			if (!empty($image))
				$this->db->write("INSERT INTO rc_recenze_obrazky (recenze_id, url) VALUES (?, ?)", "is", $recenzeID, $image);
		}

		$response->getLogger()->success("Změny uloženy");
		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function create() {

		global $config;

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$ckID = $request->get("ck_id");
		$userID = $request->get("user_id");
		$userActivated = $request->get("user_activated");
		$userIP = $request->get("ip");
		$action = $request->get("action");

		if ($action != "edit_recenze")
			$action = "add_recenze";

		if ($action == "edit_recenze" && empty($userID))
			return $response->invalidRequest();

		if ($userID === null)
			$userID = 0;

		if ($ckID === null || empty($userIP))
			return $response->invalidRequest();

		$ckID = intval($ckID);
		$userID = intval($userID);

		// Validate input

		$rules = [
			[
				"type" => "string",
				"field" => "content",
				"pretty" => "Text recenze",
				"required" => true,
				"minLength" => 20,
				"maxLength" => 1000,
			],
			[
				"type" => "number",
				"field" => "rating_profesionalita",
				"pretty" => "Hodnocení přístupu cestovní kanceláře",
				"required" => true,
				"minLength" => 1,
				"maxLength" => 5,
			],
			[
				"type" => "number",
				"field" => "rating_delegat",
				"pretty" => "Hodnocení služeb delegáta",
				"required" => true,
				"minLength" => 1,
				"maxLength" => 5,
			],
			[
				"type" => "number",
				"field" => "rating_informace",
				"pretty" => "Hodnocení shody uvedených informací s realitou",
				"required" => true,
				"minLength" => 1,
				"maxLength" => 5,
			],
			[
				"type" => "number",
				"field" => "rating_doprava",
				"pretty" => "Hodnocení dopravy",
				"required" => true,
				"minLength" => 1,
				"maxLength" => 5,
			],
		];

		// Guest
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

		$validator = new Validator($request, $log);
		if (!$validator->validate($rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		// Banlist

		$this->db->read("SELECT ip FROM rc_banlist WHERE ip = ? LIMIT 1", "s", $userIP);

		if ($this->db->lastRows() != 0)
			return $response->invalidRequest("Litujeme, ale Vaše IP adresa byla zabanována. Pokud si myslíte, že jde o omyl, neváhejte a kontaktujte nás <a href='" . $config["baseUrl"] . "/kontakt'>zde</a>");

		// CK neexistuje

		$result = $this->db->read("SELECT recenze_povoleny FROM rc_cestovky WHERE id = ? LIMIT 1", "i", $ckID);

		if ($this->db->lastRows() == 0)
			return $response->invalidRequest("Cestovka neexistuje");

		// CK aktivni

		if ($result["recenze_povoleny"] == false)
			return $response->invalidRequest("Litujeme, ale tahle cestovka není aktivní");

		// Recenze timeout

		if ($action == "add_recenze") {
			$this->db->read("SELECT id FROM rc_recenze WHERE ip = ? AND add_date BETWEEN DATE_SUB(NOW() , INTERVAL {$config['reviewPostTimeout']} MINUTE) AND NOW()", "s", $userIP);

			if ($this->db->lastRows() != 0)
				return $response->invalidRequest("Děláte to moc často");
		} else {
			$this->db->read("SELECT id FROM rc_recenze WHERE ip = ? AND edit_date BETWEEN DATE_SUB(NOW() , INTERVAL 5 MINUTE) AND NOW()", "s", $userIP);

			if ($this->db->lastRows() != 0)
				return $response->invalidRequest("Děláte to moc často");
		}

		$guestName = $request->get("guest_name");
		$guestEmail = $request->get("guest_email");
		$content = htmlspecialchars(strip_tags($request->get("content")));
		$ratingProfesionalita = $request->get("rating_profesionalita");
		$ratingDelegat = $request->get("rating_delegat");
		$ratingInformace = $request->get("rating_informace");
		$ratingDoprava = $request->get("rating_doprava");

		$status = ContentStatus::Confirm;


		if ($action == "edit_recenze") {

			$admin = $request->get("admin_view");
			$recenzeID = $request->get("recenze_id");

			$editStatus = ContentStatus::Deleted;

			$this->db->write("
				UPDATE rc_recenze
				SET
					content = COALESCE(?, content),
					rating_profesionalita = COALESCE(?, rating_profesionalita),
					rating_delegat = COALESCE(?, rating_delegat),
					rating_informace = COALESCE(?, rating_informace),
					rating_doprava = COALESCE(?, rating_doprava),
					edit_date = CURRENT_TIMESTAMP
				WHERE id = ? AND ((stav_id <> ? AND user_id = ?) OR 1 = ?)", "siiiiiiii", $content, $ratingProfesionalita, $ratingDelegat, $ratingInformace, $ratingDoprava, $recenzeID, $editStatus, $userID, $admin);
			
			if ($this->db->lastRows() > 0)
				$log->success("Změny uloženy");

			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		if ($userID == 0) {
			// Guest
			$this->db->write("
				INSERT INTO rc_recenze
					(ck_id, user_id, ip, content, rating_profesionalita, rating_delegat, rating_informace, rating_doprava, stav_id, guest_name, guest_email)
				VALUES
					(?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
				, "issiiiiiss", $ckID, $userIP, $content, $ratingProfesionalita, $ratingDelegat, $ratingInformace, $ratingDoprava, $status, $guestName, $guestEmail);
		} else {
			// Registered
			$status = (!$userActivated ? ContentStatus::Confirm : ContentStatus::OK);
			$this->db->write("
				INSERT INTO rc_recenze
					(ck_id, user_id, ip, content, rating_profesionalita, rating_delegat, rating_informace, rating_doprava, stav_id)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?)"
				, "iissiiiii", $ckID, $userID, $userIP, $content, $ratingProfesionalita, $ratingDelegat, $ratingInformace, $ratingDoprava, $status);
		}

		if ($this->db->lastRows() != 0) {
			$result = $this->db->read("SELECT LAST_INSERT_ID() AS id");
			$recenzeID = $result[0]["id"];
			$response->set("recenze_id", $recenzeID);

			if ($status == ContentStatus::Confirm)
				$log->warning("<b>Recenze byla odeslána na potvrzení administrátorem.</b> Prosím, počkejte na potvrzení nebo recenzi přidejte po aktivaci svého účtu bez čekání.");
			else
				$log->success("Recenze přidána!");
		} else {
			$log->error("Recenzi se nepodařilo přidat");
		}

		return $response->success(true);
	}

	function getLatest() {

		$status = ContentStatus::OK;

		$recenze = $this->db->read("SELECT r.id,c.nazev AS cestovka,r.add_date AS pridana,(r.rating_profesionalita+r.rating_delegat+r.rating_informace+r.rating_doprava)/4 AS final_rating FROM rc_recenze r INNER JOIN rc_cestovky c ON r.ck_id = c.id WHERE r.stav_id = ? ORDER BY r.add_date DESC LIMIT 5", "i", ContentStatus::OK);

		$prispevky = $this->db->read("
			SELECT f.id,c.nazev AS cestovka,f.add_date AS pridana, IF(LENGTH(f.guest_name) > 0, f.guest_name, u.display_name) AS uzivatel
			FROM rc_forum f
			INNER JOIN rc_cestovky c ON f.ck_id = c.id
			LEFT JOIN rc_uzivatel u ON f.user_id = u.id
			WHERE f.stav_id = ?
			ORDER BY f.add_date
			DESC LIMIT 5", "i", ContentStatus::OK);

		$response = new Response();
		$response->set("recenze", $recenze);
		$response->set("prispevky", $prispevky);

		return $response->success();
	}
}