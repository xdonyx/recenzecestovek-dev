<?php

class User extends RCViewController {
	
	function __construct() {

		global $config;

		parent::__construct();

		$result = $this->request("/api/recenze.php?route=getLatest", [], "GET");
		$this->set("latest", $result);

		$this->paths = [
			"aktivace" => "Activate",
			"aktivacni-email" => "ResendActivation",
			"login" => "Login",
			"moje-recenze" => "Recenze",
			"nastaveni" => "Settings",
			"odhlasit" => "Logout",
			"registrace" => "Register",
			"zapomenute-heslo" => "ForgotPassword",
		];

		$this->Route();
	}
	
	function ForgotPassword() {

		global $config;

		$this->set("titleText", "Zapomenuté heslo");

		if ($this->isUserLogged()) {
			header("Location:" . $config["baseUrl"]);
		}

		if (!isset($_POST["action"])) {
			$_POST["action"] = "";
		}

		if ($_POST["action"] == "password_reset") {

			if (!Validator::validateCaptcha()) {
				$log = new Logger();
				$log->error("Určitě nejste robot?");
				$this->set("output", $log);
				return;
			}

			if (empty($_POST["email"])) {
				$log = new Logger();
				$log->error("E-mailová adresa je požadovaný údaj");
				$this->set("output", $log);
				return;
			}

			$request = [
				"api_key" => $config["privateApiKey"],
				"email" => $_POST["email"],
				"g-recaptcha-response" => $_POST["g-recaptcha-response"],
			];

			$user = $this->request("/api/user.php?route=get", $request, "GET");

			if (isset($user->errors)) {
				$this->set("output", $user);
				return;
			}

			if (!empty($user->fb_id)) {
				$log = new Logger();
				$log->error("Uživatel neexistuje");
				$this->set("output", $log);
				return;
			}

			if ($user->ucet_aktivovan == false) {
				$log = new Logger();
				$log->error("O reset hesla můžete požádat jen po aktivaci účtu");
				$this->set("output", $log);
				return;
			}

			if ($user->pwd_reset != null) {
				$log = new Logger();
				$log->error("O reset hesla jste již požádali");
				$this->set("output", $log);
				return;
			}

			$request = [
				"api_key" => $config["privateApiKey"],
				"user_id" => $user->id,
				"username" => $user->username,
				"pwd_reset" => true,
			];

			$result = $this->request("/api/user.php?route=update", $request, "POST");

			if (isset($result->pwd_token)) {
				// Send email
				$subject = 'Obnova hesla';
				$message = '
					Z IP adresy ' . $_SERVER["REMOTE_ADDR"] . ' jste požádali o obnovení hesla. Pro dokončení obnovy klikněte na nasledující odkaz do 24 hodin:<br /><br />
					<div style="display:block;margin:0 auto;"><a href="' . $config["baseUrl"] . '/zapomenute-heslo?token=' . $result->pwd_token . '">' . $config["baseUrl"] . '/zapomenute-heslo?token=' . $result->pwd_token . '</a></div>
					<br /><br /><i>Tenhle e-mail byl automaticky vygenerován a odeslán na ' . $_POST["email"] . ', na základě Vaši akce na <a href="'.$config["baseUrl"].'">'.$config["applicationName"].'</a><br /></i>';
				$email = new EmailService();
				$email->SendMessage($user->email, $subject, $message);

			}

			if (isset($result->success)) {
				$result = new Logger();
				$result->success("Na uvedený e-mail byl zaslán odkaz pro dokončení obnovy hesla");
			}
			$this->set("output", $result);
		} else if (!empty($_GET["token"])) {
			$request = [
				"api_key" => $config["privateApiKey"],
				"pwd_reset_token" => $_GET["token"],
			];

			$user = $this->request("/api/user.php?route=get", $request, "GET");
			
			if (isset($user->errors)) {
				header("Location:" . $config["baseUrl"] . "/zapomenute-heslo");
			}

			if ($_POST["action"] == "do_password_reset") {
				$request = [
					"api_key" => $config["privateApiKey"],
					"user_id" => $user->id,
					"password_new" => $_POST["password_new"],
					"password_check" => $_POST["password_check"],
				];

				$result = $this->request("/api/user.php?route=update", $request, "POST");
				$this->set("output", $result);
			}
		}
	}

	function ResendActivation() {

		global $config;

		if (!Validator::validateCaptcha()) {
			$log = new Logger();
			$log->error("Určitě nejste robot?");
			$this->set("output", $log);
			return;
		}

		if (!isset($_SESSION["activaton_email"]))
			$_SESSION["activaton_email"] = false;

		if (isset($_POST["submit"])) {
			if ($_SESSION["activaton_email"]) {
				$log = new Logger();
				$log->error("Děláte to moc často");
				$this->set("output", $log);
				return;
			}

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
			]);

			$result = $this->request("/api/user.php?route=getToken", $request, "GET");

			if (isset($result->token)) {
				$_SESSION["activaton_email"] = true;
				$log = new Logger();
				$log->success("Aktivační e-mail byl odeslán");
				$this->set("output", $log);

				// Send email
				$subject = 'Znovuzaslání aktivačního e-mailu';
				$message = '
					Děkujeme za Vaši registraci. Pro aktivaci účtu prosím zadejte nasledující odkaz do Vašeho prohlížeče.<br /><br />
					<div style="display:block;margin:0 auto;"><a href="' . $config["baseUrl"] . '/aktivace?token=' . $result->token . '">' . $config["baseUrl"] . '/aktivace?token=' . $result->token . '</a></div>
					<br /><br /><i>Tenhle e-mail byl automaticky vygenerován a odeslán na ' . $_POST["email"] . ', na základě Vaši registrace na <a href="'.$config["baseUrl"].'">'.$config["applicationName"].'</a><br /></i>';
				$email = new EmailService();
				$email->SendMessage($_POST["email"], $subject, $message);
			} else {
				$this->set("output", $result);
			}
		}
	}

	function Activate()
	{
		global $config;

		if (!isset($_GET["token"]))
			header("Location: " . $config["baseUrl"]);

		$request = [
			"token" => $_GET["token"],
		];
		$result = $this->request("/api/user.php?route=activate", $request, "GET");

		$this->set("output", $result);
		$this->set("titleText", "Aktivace účtu");
	}

	function Login()
	{
		$this->set("titleText", "Přihlášení");

		global $config;

		if ($this->isUserLogged())
			header("Location: " . $config["baseUrl"]);

		if (isset($_GET["fb_error"]) && $_GET["fb_error"] == true) {
			$log = new Logger();
			$log->error("Už máte účet na stejný e-mail");
			$this->set("output", $log);
			return;
		}

		if (isset($_POST["do_login"]))
		{
			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
			]);

			$result = $this->request("/api/user.php?route=login", $request, "POST");

			if (!empty($result->id))
			{
				$resultArray = json_decode(json_encode($result), true);
				$_SESSION = array_merge($_SESSION, $resultArray);
			}

			$this->data->set("output", $result);
		}
	}

	function Recenze() {

		global $config;

		if (!$this->isUserLogged()) {
			$this->loginRedirect();
		}

		$session = new Data($_SESSION);
		$request = [
			"api_key" => $config["privateApiKey"],
			"user_id" => $session->get("id"),
		];

		$result = $this->request("/api/user.php?route=recenze", $request, "GET");
		$this->set("output", $result);
		$this->set("titleText", "Moje recenze");
	}

	function Settings() {

		$this->set("titleText", "Nastavení");

		global $config;

		if (!$this->isUserLogged()) {
			$this->loginRedirect();
		}

		$session = new Data($_SESSION);

		$log = new Logger();
		if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "update") {

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
				"user_id" => $session->get("id"),
				"username" => $session->get("username"),
				"email" => $session->get("email"),
				"user_activated" => $session->get("ucet_aktivovan"),
			]);

			$result = $this->request("/api/user.php?route=update", $request, "POST");

			$log = new Logger($result);

			$oldEmail = $_SESSION["email"];
			$this->updateSession();
			$newEmail = $_SESSION["email"];
			if ($oldEmail != $newEmail) {
				// send confirmation email
				$subject = 'Potvrzení e-mailové adresy';
				$message = '
					Vaše účet je potřeba znovu aktivovat. Pro aktivaci účtu prosím zadejte nasledující odkaz do Vašeho prohlížeče.<br /><br />
					<div style="display:block;margin:0 auto;"><a href="' . $config["baseUrl"] . '/aktivace?token=' . $result->new_token . '">' . $config["baseUrl"] . '/aktivace?token=' . $result->new_token . '</a></div>
					<br /><br /><i>Tenhle e-mail byl automaticky vygenerován a odeslán na ' . $newEmail . ', na základě Vaši akce na <a href="'.$config["baseUrl"].'">'.$config["applicationName"].'</a><br /></i>';
				$email = new EmailService();
				$email->SendMessage($newEmail, $subject, $message);

				if (!empty($oldemail)) {
					// send notification to old email
					$subject = 'Změna e-mailové adresy';
					$message = '
						Vaše e-mailová adresa se změnila na ' . $newEmail . ' na základě požadavky z IP ' . $_SERVER["REMOTE_ADDR"] . '<br /><br />
						<br /><br /><i>Tenhle e-mail byl automaticky vygenerován a odeslán na ' . $oldEmail . ', na základě Vaši akce na <a href="'.$config["baseUrl"].'">'.$config["applicationName"].'</a><br /></i>';
					$email = new EmailService();
					$email->SendMessage($oldEmail, $subject, $message);
				}
			}
		}

		$this->updateSession();

		if (!$this->isUserActivated()) {
			$log->warning("Váš účet není aktivován. Pro dokončení aktivace klikněte na odkaz z registračního e-mailu.");
		}

		$result = json_decode(json_encode($log));
		$this->data->set("output", $result);
	}

	function Register() {

		$this->set("titleText", "Registrace");

		global $config;

		if ($this->isUserLogged())
			header("Location: " . $config["baseUrl"]);

		if (isset($_POST["do_register"]))
		{
			if (!Validator::validateCaptcha()) {
				$log = new Logger();
				$log->error("Určitě nejste robot?");
				$this->set("output", $log);
				return;
			}

			$request = array_merge($_POST, [
				"api_key" => $config["privateApiKey"],
				"ip" => $_SERVER["REMOTE_ADDR"],
			]);

			$_POST["session"] = $_SESSION;
			$result = $this->request("/api/user.php?route=register", $request, "POST");
			$this->data->set("output", $result);

			if (isset($result->token)) {
				// Send email
				$subject = 'Registrace na RecenzeCestovek.cz';
				$message = '
					Děkujeme za Vaši registraci. Pro aktivaci účtu prosím zadejte nasledující odkaz do Vašeho prohlížeče.<br /><br />
					<div style="display:block;margin:0 auto;"><a href="' . $config["baseUrl"] . '/aktivace?token=' . $result->token . '">' . $config["baseUrl"] . '/aktivace?token=' . $result->token . '</a></div>
					<br /><br /><i>Tenhle e-mail byl automaticky vygenerován a odeslán na ' . $_POST["email"] . ', na základě Vaši registrace na <a href="'.$config["baseUrl"].'">'.$config["applicationName"].'</a><br /></i>';
				$email = new EmailService();
				$email->SendMessage($_POST["email"], $subject, $message);
			}
		}
	}

	function Logout() {

		global $config;

		if (!$this->isUserLogged()) {
			header("Location: " . $config["baseUrl"]);
		}

		session_unset();
		session_destroy();

		header("Location: " . $config["baseUrl"]);
	}
}