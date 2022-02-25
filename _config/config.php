<?php
session_start();

$_DEBUG = true;

$debugConfig = [
	"baseUrl" => "http://recenzecestovek-dev:8080",
	"resourceUrl" => "http://recenzecestovek-dev:8080/public",
	"applicationUrl" => "C:/wamp64/www/recenzecestovek-dev/application",
	"applicationBaseUrl" => "C:/wamp64/www/recenzecestovek-dev",

	"dbHost" => "localhost",
	"dbUser" => "root",
	"dbPass" => "",
	"dbName" => "rc_new",

	"fbAppId" => "539510313848585",
	"fbAppKey" => "b6f676df29928fd9afad6b76e58b9b84",
	"fbGraphVersion" => "v13.0",
	"contactEmail" => "martin.lukacik.sk@gmail.com",
];

$config = [

	"contactEmail" => "webmaster@recenzecestovek.cz",

	"baseUrl" => "http://beta.recenzecestovek.cz",
	"resourceUrl" => "http://beta.recenzecestovek.cz/public",
	"applicationBaseUrl" => "/home/qp012100/_sub/beta",
	"applicationUrl" => "/home/qp012100/_sub/beta/application",

	"dbHost" => "",
	"dbUser" => "",
	"dbPass" => "",
	"dbName" => "",

	"adBannerUrl" => "https://zapracipenize.cz/",
	"adBannerTitle" => "Za práci peníze",
	"adBannerFile" => "zapracipenize-cz-chranime-prava-zamestnancu.png",

	"adPanelUrl" => "https://www.recenzecestovek.cz/kontakt",
	"adPanelTitle" => "Reklamní plocha na pronájem",
	"adPanelFile" => "placeholder300x264.png",

	// Google config
	"googleAnalytics" => "UA-122302114-1",
	"googleCaptchaKey" => "6Lcdc2AUAAAAAPdYhIqha0QB6y4v_HEJ2JOt85Aq",
	"googleCaptchaPublicKey" => "6Lcdc2AUAAAAACHe5BBKZlGKhmH7gFeWrxDMOG23",

	// FB config
	"fbPublicAppId" => "1840311676277088",
	"fbAppId" => "1221303634944445",
	"fbAppKey" => "a81136bdf7ca573a13b8368234022529",
	"fbGraphVersion" => "v13.0",

	// App defaults
	"langCode" => "cz",
	"applicationName" => "RecenzeCestovek.cz",

	"uploadPath" => "public/images/upload/",
	"maxUploadSizeMB" => (int) ini_get("upload_max_filesize"),

	"privateApiKey" => "YFNUMEFfQGpFxcU29MxbbmtTQ0ReRHZN",

	"reviewPostTimeout" => 10, // min
	"replyPostTimeout" => 10, // min
	"registerTimeout" => 10, // min
];

if ($_DEBUG == true) {
	error_reporting(E_ALL);
	ini_set('display_errors', true);

	$config = array_merge($config, $debugConfig);
}

// Load the language module
include ("lang." . $config["langCode"] . ".php");

// Load the application
include ($config["applicationUrl"] . "/autoloader.php");
include ("include.php");