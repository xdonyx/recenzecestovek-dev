<?php

class RCViewController extends ViewController {

	public function __construct() {
		$this->data = new Data();
		$this->set("fbLoginUrl", $this->getFBLoginUrl());

		$this->updateSession();
		$this->updateLatest();
		$this->updateArticleList();
	}

	public static function request($target, $request, $method) {
		global $config;
		return parent::request($config["baseUrl"] . $target, $request, $method);
	}

	public function updateArticleList() {
		global $config;
		$articles = $this->request("/api/article.php?route=get", [], "GET");
		$this->set("articles", $articles);
	}

	public function getFBLoginUrl() {

		global $config;

		$fb = new Facebook\Facebook([
		  'app_id' => $config["fbAppId"],
		  'app_secret' => $config["fbAppKey"],
		  'default_graph_version' => $config["fbGraphVersion"],
		]);

		$helper = $fb->getRedirectLoginHelper();

		$permissions = ['email'];
		$loginUrl = $helper->getLoginUrl($config["baseUrl"] . '/api/fb_callback.php', $permissions);

		return $loginUrl;
	}

	public static function isUserLogged() {
		if (empty($_SESSION["id"]) || $_SESSION["id"] == 0)
			return false;

		return true;
	}

	public static function isUserActivated($session = null) {
		if (!empty($_SESSION["ucet_aktivovan"]) && $_SESSION["ucet_aktivovan"] == 1)
			return true;

		return false;
	}

	public static function isUserAdmin($session = null) {
		if (!empty($_SESSION["is_admin"]) && $_SESSION["is_admin"] == 1)
			return true;

		return false;
	}

	public function loginRedirect() {
		header("Location: " . $config["baseUrl"] . "/login");
	}

	public function updateLatest() {
		global $config;
		$result = $this->request("/api/recenze.php?route=getLatest", [], "GET");
		$this->set("latest", $result);
	}

	public function updateSession() {
		global $config;

		if ($this->isUserLogged())
		{
			$request = [
				"api_key" => $config["privateApiKey"],
				"user_id" =>  $_SESSION["id"],
			];

			$result = $this->request("/api/user.php?route=get", $request, "GET");

			if (!empty($result->id)) {
				$resultArray = json_decode(json_encode($result), true);
				$_SESSION = array_merge($_SESSION, $resultArray);
			}
		}
	}

	function sendNotifications($ck, $onlyAdmins, $isForumNotif = false) {

		global $config;

		// get subscribers and admins

		$request = [
			"api_key" => $config["privateApiKey"],
			"ck_id" => $ck->id,
		];
		$result = $this->request("/api/cestovky.php?route=getSubscribers", $request, "POST");
		
		// send emails

		foreach ($result as $user) {

			if ($onlyAdmins && $user->is_admin == 0)
				continue;

			if (!empty($user->email)) {
				$username_out = ($this->isUserLogged() ? $_SESSION["display_name"] : $_POST["guest_name"]);
				$email_out = "";
	
				$rating_out = 0;
				if ($user->is_admin) {
					$email_out = ($this->isUserLogged() ? $_SESSION["email"] : $_POST["guest_email"]);
				}

				$subject = "Nová recenze cestovky " . $ck->nazev;

				$message = "Uživatel: <b>" . $username_out . "</b><br />";

				if ($isForumNotif == true) {
					$subject = "Nový příspěvek v diskuzi " . $ck->nazev;
				} else {
					$rating_out = round(($_POST["rating_profesionalita"] + $_POST["rating_delegat"] + $_POST["rating_informace"] + $_POST["rating_doprava"]) / 4);

					$message .= "<br />Výsledné hodnocení: ";
					for ($i = 0; $i < $rating_out; ++$i) {
						$message .= "★";
					}
					$message .= "<br />";
				}
				$message .= "<br /><hr /><br />";

				$message .= "<i>" . htmlspecialchars(strip_tags($_POST["content"])) . "</i>";

				if (!$user->is_admin)
					$message .= "<br /><br /><hr /><br /><small>Tenhle e-mail byl automaticky vygenerován a odeslán na základě Vaši požadavky na odběr recenzí cestovky " . $ck->nazev . " na " . $config["applicationName"] . "<br /><br />Svoje nastavení můžete upravit <a href=\"" . $config["baseUrl"] . "/recenze/" . urlencode($ck->nazev) . "\">zde</a></small>";
				else
					$message .= ($this->isUserActivated() ? "" : "<br /><br /><hr /><br /><i><b>Recenzi je nutno potvrdit</b></i>");

				$email = new EmailService();
				$email->SendMessage($user->email, $subject, $message, $email_out);
			}
		}
	}
	
	function printRecenzeFormRating($rating, $value) {
	?>
		<div class="stars stars-input" data-value="<?=$value?>" onmousedown="setStars(this, -1);" onmouseout="rememberStars(this);" onmousemove="updateStars(this, -1);" style="font-size:30px !important">
			<input class="stars-field" type="hidden" name="<?=$rating?>" value="">
		</div>
	<?
	}

	function printRecenzeForm($requestUrl, $action, $actionText, $placeholder, $content) {
		global $config;
		global $lang;
		global $_DEBUG;

		if (!isset($content->content))
			$content->content = "";

		if (!isset($content->guest_name))
			$content->guest_name = "";
		if (!isset($content->guest_email))
			$content->guest_email = "";

		if (!isset($content->rating_profesionalita))
		 	$content->rating_profesionalita = 0;
		if (!isset($content->rating_delegat))
			$content->rating_delegat = 0;
		if (!isset($content->rating_informace))
			$content->rating_informace = 0;
		if (!isset($content->rating_doprava))
			$content->rating_doprava = 0;

		?>
		<br />
		<form method="post" id="mainform" action="<?=$requestUrl?>" enctype="multipart/form-data">
			<? if (!$this->isUserLogged()) { ?>
				<input type="text" class="form-control" placeholder="Vaše jméno" name="guest_name" value="<?=$content->guest_name?>"><br />
				<input type="email" class="form-control" placeholder="Váš e-mail" name="guest_email" value="<?=$content->guest_email?>"><br />
			<? } else if ($this->isUserLogged() && !empty($content->id)) { ?>
				<input type="hidden" name="recenze_id" value="<?=$content->id?>">
			<? } ?>
			<textarea name="content" placeholder="<?=$placeholder?>" class="d-block form-control" style="height:125px"><?=$content->content?></textarea>
			<br />
		 	<?
			?>
			<div class="container" style="margin-left:0;padding-left:0">
				<div class="row align-items-center">
					<div class="col-12 col-md-4"><?=$lang["RATING_PROFESIONALITA"]?></div>
					<div class="col-12 col-md-4"><? $this->printRecenzeFormRating("rating_profesionalita", $content->rating_profesionalita); ?></div>
				</div>
				<div class="row align-items-center">
					<div class="col-12 col-md-4"><?=$lang["RATING_DELEGAT"]?></div>
					<div class="col-12 col-md-4"><? $this->printRecenzeFormRating("rating_delegat", $content->rating_delegat); ?></div>
				</div>
				<div class="row align-items-center">
					<div class="col-12 col-md-4"><?=$lang["RATING_INFORMACE"]?></div>
					<div class="col-12 col-md-4"><? $this->printRecenzeFormRating("rating_informace", $content->rating_informace); ?></div>
				</div>
				<div class="row align-items-center">
					<div class="col-12 col-md-4"><?=$lang["RATING_DOPRAVA"]?></div>
					<div class="col-12 col-md-4"><? $this->printRecenzeFormRating("rating_doprava", $content->rating_doprava); ?></div>
				</div>
			</div><br />
			<? if(!$this->isUserLogged()) { ?>
				<br />
				<small style='font-weight:bold'>Přidat fotografie mohou jen registrovaní uživatelé.</small><br />
				<table>
					<tr>
						<td><small>Vyberte fotky</small></td>
						<td></td>
					</tr>
					<tr>
						<td>
							<input style="display:inline" class="form-control" type="file" disabled accept="image/jpeg, image/png">
						</td>
						<td>
							<input style="display:inline" class="form-control" type="file" disabled accept="image/jpeg, image/png">
						</td>
					</tr>
				</table>
				<br /><br />
				<small><strong><i><?=$lang["GUEST_CHECK_FIELDS"]?><br /><a href="<?=$config["baseUrl"]?>/registrace">Zaregistrujte se</a> pokud chcete mít možnost recenzi upravit.</i></strong></small><br /><br />
			<? } else { ?>
				<small><strong><?=$lang["UPLOAD_SIZE_LIMIT"]?></strong></small>
				<br />
				<small>Příloha</small><br />
				<input style="display:inline" class="form-control" id="foto1" type="file" name="obrazky[]" accept="image/jpeg, image/png">
				<input style="display:inline" class="form-control" id="foto2" type="file" name="obrazky[]" accept="image/jpeg, image/png">

			<? } ?>
			<br /><br />
			Přidáním recenze vyjádřujete souhlas s <strong><a href="<?=$config["baseUrl"]?>/pravidla-pouzivani" target="_blank">Pravidly používání</a></strong>.<br /><br />

			<? if (!$this->isUserLogged()) { ?>
				<div tabindex="1" class="g-recaptcha" data-sitekey="<?=$config["googleCaptchaPublicKey"]?>"></div><br />
			<? } ?>
			<button name="action" value="<?=$action?>" class="btn btn-primary"><?=$actionText?></button><br /><br />
		</form>
		<?
	}
}