<?php
include("../_config/config.php");
require_once '../vendor/autoload.php';

if (!session_id()) {
    session_start();
}

$fb = new Facebook\Facebook([
  'app_id' => $config["fbAppId"],
  'app_secret' => $config["fbAppKey"],
  'default_graph_version' => $config["fbGraphVersion"],
  'persistent_data_handler'=>'session'
]);

if (isset($_SESSION["facebook_access_token"])) {

	$fb->setDefaultAccessToken($_SESSION['facebook_access_token']);

	try {
		$response = $fb->get('/me?locale=en_US&fields=name,email,first_name,last_name');
		$userNode = $response->getGraphUser();

		$request = [
			"api_key" => $config["privateApiKey"],
			"fb_id" => $userNode->getId(),
			"email" => $userNode->getEmail(),
		];
		$user = Controller::request($config["baseUrl"] . "/api/user.php?route=get", $request, "GET");

		// account already exists
		if (isset($user->id)) {

			// fb id not set and emails are equal - 2nd registration
			if (empty($user->fb_id) && $user->email == $userNode->getEmail()) {
				header("Location: " . $config["baseUrl"] . "/login&fberror=true");
			}

			$userArray = json_decode(json_encode($user), true);
			$_SESSION = array_merge($_SESSION, $userArray);


		} else {
			$request = [
				"api_key" => $config["privateApiKey"],
				"ip" => $_SERVER["REMOTE_ADDR"],
				"username" => "fb-" . $userNode->getId(),
				"display_name" => $userNode->getName(),
				"fb_id" => $userNode->getId(),
				"email" => $userNode->getEmail(),
				"email_check" => $userNode->getEmail(),
				"password" => md5($userNode->getId()),
				"password_check" => md5($userNode->getId()),
			];
			$user = Controller::request($config["baseUrl"] . "/api/user.php?route=register", $request, "POST");

			// register success
			if (isset($user->token)) {

				if (!empty($userNode->getEmail()))
					Controller::request($config["baseUrl"] . "/api/user.php?route=activate&token=" . $user->token, [], "GET");

				// update session
				$request = [
					"api_key" => $config["privateApiKey"],
					"fb_id" => $userNode->getId(),
				];
				$user = Controller::request($config["baseUrl"] . "/api/user.php?route=get", $request, "GET");
				$userArray = json_decode(json_encode($user), true);
				$_SESSION = array_merge($_SESSION, $userArray);
			}
		}
	} catch(Exception $e) { echo $e->getMessage(); }

	header("Location: " . $config["baseUrl"]);
	return;
}

$helper = $fb->getRedirectLoginHelper();

try {
  $accessToken = $helper->getAccessToken();
} catch(Facebook\Exceptions\FacebookResponseException $e) {
  // When Graph returns an error
  echo 'Graph returned an error: ' . $e->getMessage();
  exit;
} catch(Facebook\Exceptions\FacebookSDKException $e) {
  // When validation fails or other local issues
  echo 'Facebook SDK returned an error: ' . $e->getMessage();
  exit;
}
if (isset($accessToken)) 
{
	// Logged in!
	$_SESSION['facebook_access_token'] = (string) $accessToken;
	header('Location: '.$_SERVER['REQUEST_URI']);
}
?>