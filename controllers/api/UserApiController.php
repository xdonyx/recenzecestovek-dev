<?php

class UserApiController extends ApiController {

	private $db;

	function __construct() {
		global $config;
		parent::__construct();

		$this->RegisterRoute("/api/user.php", "login", "POST");
		$this->RegisterRoute("/api/user.php", "update", "POST");
		$this->RegisterRoute("/api/user.php", "register", "POST");
		$this->RegisterRoute("/api/user.php", "get", "GET");
		$this->RegisterRoute("/api/user.php", "activate", "GET");
		$this->RegisterRoute("/api/user.php", "subscribe", "POST");
		$this->RegisterRoute("/api/user.php", "recenze", "GET");
		$this->RegisterRoute("/api/user.php", "getToken", "GET");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function getToken() {

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$email = $request->get("email");
		if (empty($email))
			return $response->invalidRequest("E-mail je požadovaný údaj");

		$result = $this->db->read("SELECT token FROM rc_uzivatel WHERE email = ? AND activation_date IS NULL AND email <> '' LIMIT 1", "s", $email);

		if (!empty($result["token"])) {
			$response->set("token", $result["token"]);
		} else {
			$response->getLogger()->error("E-mail neexistuje nebo je už aktivován");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function recenze() {

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		$userID = $request->get("user_id");

		if (!$request->isAuthorized() || empty($userID))
			return $response->unauthorized();

		$status = ContentStatus::OK;

		$recenze = $this->db->read("
			SELECT
				c.nazev AS nazev_ck,r.user_id,(r.rating_profesionalita + r.rating_delegat + r.rating_informace + r.rating_doprava) / 4 AS rating_final,r.id,r.content,r.add_date
			FROM rc_recenze r
			LEFT JOIN rc_cestovky c ON r.ck_id = c.id
			WHERE r.user_id = ? AND r.stav_id = ?
			ORDER BY r.add_date DESC"
			, "ii", $userID, $status
		);

		return json_encode($recenze);
	}

	function subscribe() {

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		$ckID = $request->get("ck_id");
		$userID = $request->get("user_id");
		$userActivated = $request->get("user_activated");
		$action = $request->get("action");

		if (!$request->isAuthorized())
			return $response->unauthorized();

		if (!isset($ckID) || !isset($userID)) 
			return $response->invalidRequest();

		$result = $this->db->read("SELECT id FROM rc_odber WHERE ck_id = ? AND user_id = ? LIMIT 1", "ii", $ckID, $userID);

		$subscribed = ($this->db->lastRows() == 0 ? false : true);

		switch ($action) {
			case "subscribe":
				if (!$userActivated)
					return $response->invalidRequest("Musíte aktivovat svůj účet");

				if ($subscribed)
					return $response->invalidRequest("Odběr je už aktivní");

				$this->db->write("INSERT INTO rc_odber (ck_id, user_id) VALUES (?, ?)", "ii", $ckID, $userID);

				$log->success("Odteď odebíráte recenze a diskuzi pro tuhle cestovku");

				return $response->success(RESPONSE_INCLUDE_LOG);

			case "unsubscribe":
				if (!$subscribed)
					return $response->invalidRequest("Odběr je už zrušen");

				$this->db->write("DELETE FROM rc_odber WHERE ck_id = ? AND user_id = ?", "ii", $ckID, $userID);

				if ($this->db->lastRows() > 0) {
					$log->success("Odběr recenzí a diskuze pro tuhle cestovku byl zrušen");
				}

				return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$response->set("subscribed", $subscribed);

		return $response->success();
	}

	function get() {

		$request = $this->request;

		$response = $this->response;

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$userID = $request->get("user_id");
		$fbID = $request->get("fb_id");
		$username = $request->get("login_username");
		$email = $request->get("email");
		$passwordResetToken = $request->get("pwd_reset_token");

		if (empty($email) && empty($userID) && empty($username) && empty($passwordResetToken) && empty($fbID))
			return $response->invalidRequest();

		if (empty($email) && !empty($username))
			$email = $username;

		$result = Array();

		if (!empty($passwordResetToken)) {
			$result = $this->db->read("SELECT * FROM rc_uzivatel WHERE pwd_reset_token = ? AND pwd_reset BETWEEN DATE_SUB(NOW() , INTERVAL 24 HOUR) AND NOW() LIMIT 1", "s", $passwordResetToken);
		} else if (!empty($fbID)) {
			$result = $this->db->read("SELECT * FROM rc_uzivatel WHERE email = ? OR fb_id = ? LIMIT 1", "ss", $email, $fbID);
		} else if (!empty($userID)) {
			$result = $this->db->read("SELECT * FROM rc_uzivatel WHERE id = ? LIMIT 1", "i", $userID);
		} else {
			$result = $this->db->read("SELECT * FROM rc_uzivatel WHERE fb_id = ? OR email = ? OR username = ? LIMIT 1", "sss", $fbID, $email, $username);
		}

		if ($this->db->lastRows() > 0) {
			$result["ucet_aktivovan"] = !empty($result["activation_date"]);
			$request->set("password_verify", $result["password"]);
			unset($result["password"]);
			unset($result["token"]);
			unset($result["activation_date"]);
		} else {
			return $response->invalidRequest("Uživatel neexistuje");
		}

		return json_encode($result);
	}

	function login() {

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$username = $request->get("login_username");
		$password = $request->get("password");

		$rules = [
			[
				"type" => "string",
				"field" => "login_username",
				"pretty" => "Přihlašovací jméno",
				"required" => true,
			],
			[
				"type" => "string",
				"field" => "password",
				"pretty" => "Heslo",
				"required" => true,
			]
		];

		$validator = new Validator($request, $log);
		if (!$validator->validate($rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$request->set("email", $username);
		$request->set("admin_view", 1);

		$result = json_decode($this->get()); // sets password_verify in request
		$passwordVerify = $request->get("password_verify");

		if (!isset($result->id)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		if (password_verify($password, $passwordVerify)) {
			unset($result->password);
			$this->db->write("UPDATE rc_uzivatel SET pwd_reset = NULL, pwd_reset_token = '' WHERE id = ?", "i", $result->id);
			return json_encode($result);
		} else {
			$log->error("Nesprávné heslo");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function update() {

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$userID = $request->get("user_id");
		$emailTrusted = $request->get("email");
		$userActivated = $request->get("user_activated");

		$displayName = strip_tags_ex($request->get("display_name_new"));
		$email = strip_tags_ex($request->get("email_current"));
		$emailNew = strip_tags_ex($request->get("email_new"));
		$emailCheck = strip_tags_ex($request->get("email_check"));
		$password = $request->get("password_current");
		$passwordNew = $request->get("password_new");
		$passwordCheck = $request->get("password_check");
		$passwordReset = $request->get("pwd_reset");
		$fbID = $request->get("fb_id");
		$replyNotif = $request->get("reply_notif");
		$replyNotif = ($replyNotif == 0 ? 0 : 1);
		$admin = null;

		$rules = [
			[
				"type" => "string",
				"field" => "display_name_new",
				"pretty" => "Zobrazované jméno",
				"required" => false,
				"minLength" => 3,
				"maxLength" => 30,
			],
			[
				"type" => "email",
				"field" => "email_new",
				"pretty" => "Nová e-mailová adresa",
				"minLength" => 8,
				"maxLength" => 30,
			],
			[
				"type" => "string",
				"field" => "password",
				"pretty" => "Heslo",
				"minLength" => 6,
				"maxLength" => 127,
			],
			[
				"type" => "string",
				"field" => "password_new",
				"pretty" => "Nové heslo",
				"minLength" => 6,
				"maxLength" => 127,
			],
		];

		if ($request->get("action") == "set_admin") {
			$admin = $request->get("admin");
		}

		$validator = new Validator($request, $log);
		$validator->validateMatch($passwordNew, $passwordCheck, "Nové heslo a kontrola");
		$validator->validateMatch($emailNew, $emailCheck, "Nové e-mailové adresy");
		if (!$validator->validate($rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$user = null;
		if (!empty($password) && !empty($passwordNew)) {
			$request->set("login_username", $request->get("username"));
			$request->set("password", $password);
			$request->set("user_id", null);
			$user = json_decode($this->login());

			if (isset($user->errors)) {
				return json_encode($user);
			}
		} else {
			$user = json_decode($this->get());

			if (isset($user->errors)) {
				return json_encode($user);
			}
		}

		if (!empty($emailNew)) {

			if ($userActivated != 1 && !empty($emailTrusted)) {
				return $response->invalidRequest("E-mail nelze změnit, dokud nepotvrdíte svůj aktuální e-mail");
			}

			$validator->validateMatch($emailTrusted, $email, "Aktuální e-mailové adresy");
			if (!$validator->isValid())
				return $response->success(RESPONSE_INCLUDE_LOG);

			if (!strcmp($emailTrusted, $emailNew))
				return $response->invalidRequest("Nový e-mail se nemůže shodovat s aktuálním");

			$result = $this->db->read("SELECT id FROM rc_uzivatel WHERE email = ? LIMIT 1", "s", $emailNew);

			if ($this->db->lastRows() != 0) {
				return $response->invalidRequest("E-mailová adresa je obsazena");
			}

			$log->warning("E-mailová adresa se změnila, účet je třeba znovu aktivovat");
			$token = md5(uniqid(rand().$emailNew, true));
			$this->db->write("UPDATE rc_uzivatel SET token = ?, activation_date = NULL WHERE id = ?", "si", $token, $userID);
			$response->set("new_token", $token);
		}

		if (!empty($passwordNew))
			$passwordNew = password_hash($passwordNew, PASSWORD_DEFAULT);
		else
			$passwordNew = null;

		$passwordToken = NULL;
		if ($passwordReset) {
			$passwordToken = md5(uniqid(rand().$email, true));

			$response->set("pwd_token", $passwordToken);
		}

		$this->db->write("
			UPDATE rc_uzivatel
			SET
				display_name = COALESCE(?, display_name),
				email = COALESCE(?, email),
				password = COALESCE(?, password),
				reply_notif = COALESCE(?, reply_notif),
				is_admin = COALESCE(?, is_admin),
				pwd_reset = IF(1 = ?, NOW(), NULL),
				pwd_reset_token = COALESCE(?, ''),
				fb_id = COALESCE(?, fb_id)
			WHERE id = ?","sssiiissi", $displayName, $emailNew, $passwordNew, $replyNotif, $admin, $passwordReset, $passwordToken, $fbID, $userID
		);

		if ($this->db->lastRows() != 0) {
			if ($request->get("action") == "set_admin") {
				$result = $this->db->read("SELECT username FROM rc_uzivatel WHERE id = ? LIMIT 1", "i", $userID);
				$log->success("Úspěch: <b>" . $result["username"] . "</b> (ID: " . $userID . ") je odteď <b>" . (!empty($admin) ? "administrátor" : "uživatel") . "</b>");
			} else {
				$log->success("Změny byly uloženy");
			}
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function register() {

		global $config;

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		if (!$request->isAuthorized()) {
			return $response->unauthorized();
		}

		$ip = $request->get("ip");
		$username = strip_tags($request->get("username"));
		$password = strip_tags($request->get("password"));
		$passwordCheck = strip_tags($request->get("password_check"));
		$email = strip_tags($request->get("email"));
		$emailCheck = strip_tags($request->get("email_check"));
		$displayName = strip_tags($request->get("display_name"));
		$fbID = strip_tags($request->get("fb_id"));

		if (empty($fbID) && strpos("fb-", $username) !== false) {
			return $response->invalidRequest("Uživatelské jméno obsahuje nepovolené znaky");
		}

		if (empty($ip)) {
			return $response->invalidRequest();
		}

		// fb user without email
		if (!empty($fbID) && empty($email)) {
			$request->set("email", "pass@check.com");
			$request->set("email_check", "pass@check.com");
		}

		$validator = new Validator($request, $log);
		$validator->validateMatch($password, $passwordCheck, "Hesla");
		$validator->validateMatch($email, $emailCheck, "E-mailové adresy");

		if (!$validator->validate()) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		// Check timeout

		$rows = $this->db->read("
			SELECT id
			FROM rc_uzivatel
			WHERE ip = ? AND registration_date BETWEEN DATE_SUB(NOW() , INTERVAL {$config["registerTimeout"]} MINUTE) AND NOW()
			LIMIT 1"
			, "s", $ip);
		if (count($rows) > 0)
			$log->error("Děláte to moc často");

		if($log->getErrorCount() == 0) {

			$result = $this->db->read("SELECT id FROM rc_uzivatel WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1", "ss", $username, $email);

	    	if($this->db->lastRows() != 0) {
				$log->error("Uživatel se stejným jménem nebo e-mailem existuje");
			} else {
				$token = md5(uniqid(rand().$email, true));
				
				$password = password_hash($password, PASSWORD_DEFAULT);

				$success = $this->db->write("INSERT INTO rc_uzivatel (username, password, display_name, email, ip, token, fb_id) VALUES (?, ?, ?, ?, ?, ?, ?)", "sssssss", $username, $password, $displayName, $email, $ip, $token, $fbID);

				if ($success = 1) {
					$response->set("token", $token);
					$log->success("Registrace proběhla úspěšně, na uvedený e-mail Vám přijde zpráva s potvrzovacím odkazem. Pokud nedorazila po dobu více jak 5 minut, prověřte složku Spam nebo požádejte o znovuzaslání e-mailu.");
				}
			}
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function activate() {

		$request = $this->request;

		$response = $this->response;

		$log = $response->getLogger();

		$token = $request->get("token");

		if (empty($token)) {
			return $response->invalidRequest("Neplatný token");
		}

		$this->db->write("UPDATE rc_uzivatel SET activation_date = NOW() WHERE token = ? AND activation_date IS NULL LIMIT 1", "s", $token);

		if($this->db->lastRows() == 0)
			return $response->invalidRequest("Neplatný token");

		$log->success("Aktivace proběhla úspěšně");

		return $response->success(RESPONSE_INCLUDE_LOG);
	}
}