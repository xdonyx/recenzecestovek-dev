<?php

class AdminApiController extends ApiController {

	private $db;

	function __construct() {
		global $config;
		parent::__construct();

		$this->RegisterRoute("/api/admin.php", "getHeader", "GET");
		$this->RegisterRoute("/api/admin.php", "ban", "POST");
		$this->RegisterRoute("/api/admin.php", "unban", "POST");
		$this->RegisterRoute("/api/admin.php", "getBanlist", "GET");
		$this->RegisterRoute("/api/admin.php", "getUsers", "GET");
		$this->RegisterRoute("/api/admin.php", "lookup", "GET");
		$this->RegisterRoute("/api/admin.php", "top100", "GET");
		$this->RegisterRoute("/api/admin.php", "getRecenze", "GET");
		$this->RegisterRoute("/api/admin.php", "getPrispevky", "GET");
		$this->RegisterRoute("/api/admin.php", "setContentStatus", "POST");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function setContentStatus() {

		$request = $this->request;
		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$contentID = $request->get("content_id");
		$status = $request->get("status");

		$contentType = $request->get("content_type");

		if (!isset($contentID) || !isset($status)) {
			return $response->invalidRequest();
		}

		if ($contentType == "diskuze")
			$result = $this->db->write("UPDATE rc_forum SET stav_id = ? WHERE id = ?", "ii", $status, $contentID);
		else
			$result = $this->db->write("UPDATE rc_recenze SET stav_id = ? WHERE id = ?", "ii", $status, $contentID);

		if ($this->db->lastRows() > 0) {

			switch ($status) {
				case ContentStatus::OK:
					$log->success("Potvrzení obsahu proběhlo úspěšně");
					break;
				default:
					$log->success("Smazání obsahu do koše proběhlo úspěšně");
			}
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function getRecenze() {
		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$status = $request->get("status");

		$result = $this->db->read("
			SELECT
				r.id, r.user_id, r.ip, r.content, r.add_date, r.rating_profesionalita, r.rating_delegat, r.rating_informace, r.rating_doprava, r.guest_name, r.guest_email,
				u.username, u.display_name, u.email,
				r.ck_id, c.nazev,
				ro.url
			FROM
				rc_recenze r
			LEFT JOIN rc_uzivatel u ON r.user_id = u.id
			INNER JOIN rc_cestovky c ON r.ck_id = c.id
			LEFT JOIN rc_recenze_obrazky ro ON r.id = ro.recenze_id
			WHERE r.stav_id = ?
			ORDER BY r.add_date DESC"
		, "i", $status);

		$lastID = 0;

		$output = Array();
		for ($i = 0; $i < count($result); ++$i) {
			
			$r = $result[$i];

			if ($r["id"] != $lastID || $lastID == 0) {
				$lastID = $r["id"];
				array_push($output, $r);
				$output[count($output) - 1]["prilohy"] = Array();
			}

			if (!empty($r["url"])) {
				array_push($output[count($output) - 1]["prilohy"], $r["url"]);
			}

			unset($output[count($output) - 1]["url"]);
		}

		return json_encode($output);
	}

	function getPrispevky() {
		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$result = $this->db->read("
			SELECT
				f.id, f.user_id, f.ip, f.content, f.add_date, f.guest_name, f.guest_email,
				u.username, u.display_name, u.email,
				f.ck_id, c.nazev
			FROM
				rc_forum f
			LEFT JOIN rc_uzivatel u ON f.user_id = u.id
			INNER JOIN rc_cestovky c ON f.ck_id = c.id
			WHERE f.stav_id = ?
			ORDER BY f.add_date DESC"
		, "i", ContentStatus::Deleted);

		return json_encode($result);
	}

	function getHeader() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$ok = ContentStatus::OK;
		$deleted = ContentStatus::Deleted;
		$confirm = ContentStatus::Confirm;

		$output = $this->db->read("SELECT SUM(IF(stav_id = ?, 1, 0)) AS pocet_recenzi, SUM(IF(stav_id = ?, 1, 0)) AS pocet_kos, SUM(IF(stav_id = ?, 1, 0)) AS pocet_potvrdit FROM rc_recenze LIMIT 1", "iii", $ok, $deleted, $confirm);

		$result = $this->db->read("SELECT SUM(IF(recenze_povoleny = 1, 1, 0)) AS aktivnich_ck, SUM(IF(recenze_povoleny = 0, 1, 0)) AS neaktivnich_ck FROM rc_cestovky LIMIT 1");

		$output = array_merge($output, $result);

		$result = $this->db->read("SELECT COUNT(id) AS pocet_uzivatelu FROM rc_uzivatel LIMIT 1");

		$output = array_merge($output, $result);

		return json_encode($output, JSON_NUMERIC_CHECK);
	}

	function top100() {

		$result = $this->db->read("
			SELECT
				c.*, AVG(r.rating_profesionalita + r.rating_delegat + r.rating_informace + r.rating_doprava) / 4 AS prumerne_hodnoceni, COUNT(r.id) AS pocet_recenzi
			FROM rc_cestovky c
			LEFT JOIN rc_recenze r ON c.id = r.ck_id
			WHERE r.stav_id = ?
            GROUP BY c.id
            ORDER BY prumerne_hodnoceni DESC, pocet_recenzi DESC
		", "i", ContentStatus::OK);

		return json_encode($result);
	}

	function lookup() {
		

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$ip = $request->get("ip");

		if (!empty($ip)) {

			$result = $this->db->read("SELECT id, username, ip FROM rc_uzivatel WHERE ip = ?", "s", $ip);

			$response->set("uzivatele", $result);

			$result = $this->db->read("
				SELECT
					u.username, u.ip AS user_ip, r.id AS recenze_id, r.ip AS recenze_ip, r.content, c.nazev, c.id AS ck_id, u.display_name, r.stav_id, r.guest_name, r.guest_email
				FROM rc_recenze r
				LEFT JOIN rc_uzivatel u ON r.user_id = u.id 
				LEFT JOIN rc_cestovky c ON c.id = r.ck_id
				WHERE r.ip = ? OR u.ip = ?", "ss", $ip, $ip);

			$response->set("recenze", $result);

			return $response->success();
		}
		
		$result = $this->db->read("
			SELECT
				id,username,email,ip
			FROM rc_uzivatel
			WHERE
				ip IN
					(SELECT ip FROM rc_uzivatel GROUP BY ip HAVING COUNT(*) > 1)
			ORDER BY ip"
		);

		return json_encode($result);
	}

	function getUsers() {

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		
		$result = $this->db->read("SELECT id, username, display_name, ip, email, registration_date, IF(activation_date IS NULL, 0, 1) AS activated, fb_id, is_admin FROM rc_uzivatel");

		return json_encode($result);
	}

	function getBanlist() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$result = $this->db->read("SELECT b.id, u.username AS admin, b.admin_id, b.ip, b.datum FROM rc_banlist b INNER JOIN rc_uzivatel u ON b.admin_id = u.id ORDER BY b.datum DESC");

		return json_encode($result);
	}

	function ban() {

		$request = $this->request;
		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$adminID = $request->get("admin_id");
		$banIP = $request->get("ip");

		if (empty($banIP)) {
			return $response->invalidRequest("IP adresa nemůže být prázdná");
		}

		$this->db->read("SELECT id FROM rc_banlist WHERE ip = ? LIMIT 1", "s", $banIP);

		if ($this->db->lastRows() > 0) {
			return $response->invalidRequest("IP <b>" . $banIP . "</b> je už zabanována");
		}

		$this->db->write("INSERT INTO rc_banlist (admin_id, ip) VALUES (?, ?)", "is", $adminID, $banIP);

		$log->warning("IP adresa <b>" . $banIP . "</b> byla zabanována!");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function unban() {

		$request = $this->request;
		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$banIP = $request->get("ip");

		if (empty($banIP) && empty($banID)) {
			return $response->invalidRequest("IP adresa nemůže být prázdná");
		}

		$this->db->write("DELETE FROM rc_banlist WHERE ip = ? LIMIT 1", "s", $banIP);

		if ($this->db->lastRows() > 0)
			$log->warning("Ban IP adresy <b>" . $banIP . "</b> byl zrušen");
		else
			$log->error("Není co smazat");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}
}