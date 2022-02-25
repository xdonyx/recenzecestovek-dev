<?php

class CestovkaApiController extends ApiController {

	private $db;

	function __construct() {
		global $config;
		parent::__construct();

		$this->RegisterRoute("/api/cestovky.php", "add", "POST");
		$this->RegisterRoute("/api/cestovky.php", "get", "GET");
		$this->RegisterRoute("/api/cestovky.php", "search", "GET");
		$this->RegisterRoute("/api/cestovky.php", "recenze", "GET");
		$this->RegisterRoute("/api/cestovky.php", "update", "POST");
		$this->RegisterRoute("/api/cestovky.php", "delete", "POST");
		$this->RegisterRoute("/api/cestovky.php", "getSubscribers", "GET");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function getSubscribers() {

		$request = $this->request;
		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$ckID = $request->get("ck_id");
		if (empty($ckID))
			return $response->invalidRequest();

		$result = $this->db->read("SELECT u.id, u.email, u.is_admin FROM rc_uzivatel u LEFT JOIN (SELECT ck_id, user_id FROM rc_odber WHERE ck_id = ?) o ON u.id = o.user_id WHERE (o.ck_id IS NOT NULL OR u.is_admin = 1) AND u.activation_date IS NOT NULL", "i", $ckID);

		return json_encode($result);
	}

	function update() {

		$request = $this->request;
		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$ckID = $request->get("ck_id");
		$value = $request->get("value");
		$action = $request->get("action");

		if (empty($ckID))
			return $response->invalidRequest();

		$result = 0;
		if ($action == "archiv") {
			$result = $this->db->write("UPDATE rc_cestovky SET archiv_viditelny = ? WHERE id = ?", "ii", $value, $ckID);
		} else if ($action == "prispevky") {
			$result = $this->db->write("UPDATE rc_cestovky SET prispevky_povoleny = ? WHERE id = ?", "ii", $value, $ckID);
		} else {
			$result = $this->db->write("UPDATE rc_cestovky SET recenze_povoleny = ? WHERE id = ?", "ii", $value, $ckID);
		}

		if ($result > 0) {

			$result = $this->db->read("SELECT nazev FROM rc_cestovky WHERE id = ? LIMIT 1", "i", $ckID);

			if ($action == "archiv")
				$log->success("<b>Archiv</b> pro cestovku <b>" . $result["nazev"] . "</b> je odteď " . ($value == 0 ? "<b>neviditelný</b>" : "<b>viditelný</b>"));
			else if ($action == "prispevky")
				$log->success("Přidávání <b>příspěvků</b> pro cestovku <b>" . $result["nazev"] . "</b> je odteď " . ($value == 0 ? "<b>zakázáno</b>" : "<b>povoleno</b>"));
			else
				$log->success("Přidávání <b>recenzí</b> pro cestovku <b>" . $result["nazev"] . "</b> je odteď " . ($value == 0 ? "<b>zakázáno</b>" : "<b>povoleno</b>"));
		} else {
			return $response->invalidRequest("Děláte to moc často");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function delete() {

		$request = $this->request;
		$response = $this->response;
		
		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$ckID = $request->get("ck_id");


		$ck = $this->db->read("SELECT nazev FROM rc_cestovky WHERE id = ? LIMIT 1", "i", $ckID);

		if ($this->db->lastRows() == 0)
			return $response->invalidRequest("Cestovka neexistuje");

		$recenze = $this->db->read("SELECT id FROM rc_recenze WHERE ck_id = ?", "i", $ckID);
		
		$prilohy = Array();
		$smazanePrilohy = 0;
		$smazaneKomentare = 0;
		foreach ($recenze as &$r) {

			$p = $this->db->read("SELECT url FROM rc_recenze_obrazky WHERE recenze_id = ?", "i", $r["id"]);
			$prilohy = array_merge($prilohy, $p);

			$smazanePrilohy = $this->db->write("DELETE FROM rc_recenze_obrazky WHERE recenze_id = ?", "i", $r["id"]);
			$smazaneKomentare = $this->db->write("DELETE FROM rc_komentare WHERE recenze_id = ?", "i", $r["id"]);
		}

		$smazaneRecenze = $this->db->write("DELETE FROM rc_recenze WHERE ck_id = ?", "i", $ckID);
		$smazaneDiskuse = $this->db->write("DELETE FROM rc_forum WHERE ck_id = ? LIMIT 1", "i", $ckID);
		$smazaneCestovky = $this->db->write("DELETE FROM rc_cestovky WHERE id = ? LIMIT 1", "s", $ckID);


		$response->set("smazane_cestovky", $smazaneCestovky);
		$response->set("smazane_recenze", $smazaneRecenze);
		$response->set("smazane_prilohy", $smazanePrilohy);
		$response->set("smazane_komentare", $smazaneKomentare);
		$response->set("smazane_diskuse", $smazaneDiskuse);
		$response->set("prilohy_unlink", $prilohy);

		if ($smazaneCestovky == 0)
			return $response->invalidRequest("Cestovka neexistuje");

		$log->warning("Cestovka <b>" . $ck["nazev"] . "</b> byla vymazána (" . $smazaneRecenze . " recenzí, " . $smazanePrilohy . " příloh, " . $smazaneKomentare . " komentárů, " . $smazaneDiskuse . " příspěvků ve fóru)");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function add() {

		$request = $this->request;
		$response = $this->response;

		$log = $response->getLogger();

		$nazev = urldecode($request->get("nazev"));

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$rules = [
			[
				"type" => "string",
				"field" => "nazev",
				"pretty" => "Název cestovky",
				"required" => true,
				"minLength" => 3,
				"maxLength" => 30,
			],
		];

		$validator = new Validator($request, $log);
		if ($validator->validate($rules)) {

			$result = $this->db->read("SELECT id FROM rc_cestovky WHERE LOWER(nazev) = LOWER(?)", "s", $nazev);

			if (count($result) == 0) {

				$success = $this->db->write("INSERT INTO rc_cestovky (nazev) VALUES (?)", "s", $nazev);

				if ($success)
					$log->success("Cestovka <b>" . $nazev . "</b> byla přidána!");
				else
					$log->error("Nepodařilo se přidat cestovku");

			} else {
				$log->error("Cestovka s názvem <b>" . $nazev . "</b> existuje");
			}
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function get() {

		$request = $this->request;
		$response = $this->response;

		$ckParam = urldecode($request->get("ck"));

		if (!empty($ckParam)) {

			$id = (is_numeric($ckParam) ? $ckParam : 0);
			$result = $this->db->read("SELECT id, nazev, recenze_povoleny, prispevky_povoleny, archiv_viditelny FROM rc_cestovky WHERE id = ? OR nazev = ? LIMIT 1", "is", $id, $ckParam);

			if ($this->db->lastRows() == 0)
				return $response->invalidRequest("Cestovka nenalezena");
			
			return json_encode($result);
		}

		$result = $this->db->read("SELECT id, nazev, recenze_povoleny, prispevky_povoleny, archiv_viditelny FROM rc_cestovky ORDER BY nazev ASC");

		return json_encode($result);
	}

	function recenze() {

		$request = $this->request;
		$response = $this->response;

		$ckID = $request->get("ck_id");
		$request->set("ck", $ckID);
		if (empty($ckID)) {
			return $response->invalidRequest();
		}

		$ck = json_decode($this->get());
		$ck->prumerne_hodnoceni = null;


		if (isset($ck->errors)) {
			return json_encode($ck);
		}

		$status = ContentStatus::OK;

		$result = $this->db->read("SELECT (AVG(rating_profesionalita + rating_delegat + rating_informace + rating_doprava) / 4) AS prumer FROM rc_recenze WHERE ck_id = ? AND stav_id = ? LIMIT 1", "ii", $ck->id, $status);
		$ck->prumerne_hodnoceni = $result["prumer"];

		$page = $request->get("page");
		$perPage = 1;
		$LIMIT = "";
		if (isset($page) && is_numeric($page) && $page > 0) {
			--$page;
			$LIMIT = " LIMIT " . ($perPage * $page) . ", " . $perPage;
		}

		$recenze = $this->db->read("
			SELECT
				r.id, r.ck_id, r.user_id,
				r.uzivatel, r.email,
				r.ip,
				r.content, r.add_date, r.edit_date,
				r.rating_profesionalita, r.rating_delegat, r.rating_informace, r.rating_doprava,
				r.stav_id,
				(SELECT ro.url FROM rc_recenze_obrazky ro WHERE ro.recenze_id = r.id ORDER BY ro.id ASC LIMIT 1) AS img_url_1,
				(SELECT ro.url FROM rc_recenze_obrazky ro WHERE ro.recenze_id = r.id ORDER BY ro.id DESC LIMIT 1) AS img_url_2,
				k.id AS komentar_id, k.user_id AS komentar_user_id, k.content AS komentar_content, k.add_date AS komentar_add_date, k.edit_date AS komentar_edit_date, k.ip AS komentar_ip,
				IF(u.display_name != '', u.display_name, u.username) AS komentar_uzivatel
			FROM
			(
				SELECT
					r.*,
					IF(u.display_name != '', u.display_name, IF(u.username != '', u.username, r.guest_name)) AS uzivatel,
					IF(u.email != '', u.email, IF(u.email != '', u.email, r.guest_email)) AS email
				FROM rc_recenze r
				LEFT JOIN rc_uzivatel u ON r.user_id = u.id
				WHERE ck_id = ? AND stav_id = ? {$LIMIT}
			) AS r
			LEFT JOIN rc_komentare k ON r.id = k.recenze_id AND k.stav_id = ?
			LEFT JOIN rc_uzivatel u ON k.user_id = u.id
			ORDER BY r.add_date DESC , r.id DESC, k.add_date DESC , k.id DESC
			", "iii", $ck->id, $status, $status);


		$recenze_ = Array();

		$lastRecenzeId = 0;

		for ($i = 0; $i < count($recenze); ++$i) {

			$r = &$recenze[$i];

			if ($lastRecenzeId != $r["id"]) {
				$lastRecenzeId = $r["id"];
				$r["komentare"] = Array();
				array_push($recenze_, $r);
			}

			if ($r["komentar_id"] != null) {
				//echo $recenzeIndex . " ";
				//echo var_dump($recenze[$recenzeIndex]);
				$recenzeIndex = count($recenze_) - 1;
				array_push($recenze_[$recenzeIndex]["komentare"], [
					"id" => $r["komentar_id"],
					"user_id" => $r["komentar_user_id"],
					"uzivatel" => $r["komentar_uzivatel"],
					"ip" => $r["komentar_ip"],
					"content" => $r["komentar_content"],
					"add_date" => $r["komentar_add_date"],
					"edit_date" => $r["komentar_edit_date"],
				]);

				$r = &$recenze_;
				if (isset($r["komentare"])) {
					unset($r["komentar_id"]);
					unset($r["komentar_user_id"]);
					unset($r["komentar_uzivatel"]);
					unset($r["komentar_ip"]);
					unset($r["komentar_content"]);
					unset($r["komentar_add_date"]);
					unset($r["komentar_edit_date"]);
				}
			}
		}

		$ck->recenze = $recenze_;
/*
		// Get recenze
		if ($request->isAuthorized() && $request->get("admin_view") == 1) {
			$recenze = $this->db->read("
				SELECT
					r.id, r.ck_id, r.user_id,
					IF(u.display_name != '', u.display_name, IF(u.username != '', u.username, r.guest_name)) AS uzivatel,
					r.ip, IF(u.email != '', u.email, IF(u.email != '', u.email, r.guest_email)) AS email,
				    r.content, r.add_date,
				    r.rating_profesionalita, r.rating_delegat, r.rating_informace, r.rating_doprava,
				    r.stav_id,
				    (SELECT ro.url FROM rc_recenze_obrazky ro WHERE ro.recenze_id = r.id ORDER BY ro.id ASC LIMIT 1) AS img_url_1,
				    (SELECT ro.url FROM rc_recenze_obrazky ro WHERE ro.recenze_id = r.id ORDER BY ro.id DESC LIMIT 1) AS img_url_2
				FROM rc_recenze r
				LEFT JOIN rc_uzivatel u ON r.user_id = u.id
				WHERE r.ck_id = ? AND r.stav_id = ?
				ORDER BY r.add_date DESC" . $limit,
				"ii", $ck->id, $contentStatus
			);
		} else {
			$recenze = $this->db->read("
				SELECT
					r.id, r.ck_id, r.user_id,
					IF(u.display_name != '', u.display_name, IF(u.username != '', u.username, r.guest_name)) AS uzivatel,
				    r.content, r.add_date,
				    r.rating_profesionalita, r.rating_delegat, r.rating_informace, r.rating_doprava,
				    r.stav_id,
				    (SELECT ro.url FROM rc_recenze_obrazky ro WHERE ro.recenze_id = r.id ORDER BY ro.id ASC LIMIT 1) AS img_url_1,
				    (SELECT ro.url FROM rc_recenze_obrazky ro WHERE ro.recenze_id = r.id ORDER BY ro.id DESC LIMIT 1) AS img_url_2
				FROM rc_recenze r
				LEFT JOIN rc_uzivatel u ON r.user_id = u.id
				WHERE r.ck_id = ? AND r.stav_id = ?
				ORDER BY r.add_date DESC" . $limit,
				"ii", $ck->id, $contentStatus
			);
		}
*//*
		if (!empty($recenze)) {
			foreach ($recenze as &$r) {

				// Get komentare
				$komentare = Array();
				if (!$request->isAuthorized() || $request->get("admin_view") != 1) {
					$komentare = $this->db->read("
						SELECT k.id, k.recenze_id, k.user_id, u.display_name AS uzivatel, k.content, k.add_date
						FROM rc_komentare k
						INNER JOIN rc_uzivatel u
						ON k.user_id = u.id
						WHERE k.recenze_id = ? AND stav_id = ?
						ORDER BY add_date DESC
					", "ii", $r["id"], $contentStatus);
				} else {
					$komentare = $this->db->read("
						SELECT k.id, k.recenze_id, k.user_id, u.display_name AS uzivatel, k.content, k.add_date, k.ip
						FROM rc_komentare k
						INNER JOIN rc_uzivatel u
						ON k.user_id = u.id
						WHERE k.recenze_id = ? AND stav_id = ?
						ORDER BY add_date DESC
					", "ii", $r["id"], $contentStatus);
				}

				if (!empty($komentare))
					$r["komentare"] = $komentare;

			}

			$ck->recenze = $recenze;
		}*/

		return json_encode($ck);
	}
}