<?php

include ("./_config/config.php");

//header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET["ck_id"]) || empty($_GET["ck_nazev"])) {
	$log = new Logger();
	$log->error("Neplatná požadavka");
	unset($log->logs);
	unset($log->warnings);
	echo json_encode($log);
	return;
}

if (empty($_GET["page"]))
	$_GET["page"] = 1;

$request = [
	"api_key" => $config["privateApiKey"],
	"ck_id" => (int) $_GET["ck_id"],
	"page" => (int) $_GET["page"],
	"admin_view" => RCViewController::isUserAdmin(),
];

$result = Controller::request($config["baseUrl"] . "/api/diskuze.php?route=get", $request, "GET");

if (count($result) == 0 && $_GET["page"] == 1) {
	echo "0";
	return;
}
if (count($result) == 0 && $_GET["page"] != 1) {
	echo "1";
	return;
}

foreach ($result as $i => $p) {

	if (!empty($p->guest_name)) {
		$p->display_name = $p->guest_name;
	}
?>

	<div class="card" style="border:1px solid maroon">
		<div class="card-header row">
			<div class="col" style="padding:0 !important">
				
			<small>Napsal/a</small> <strong><?=$p->display_name?></strong> - <?=date("d.m.Y H:i", strtotime($p->add_date))?></div>
		</div>
		<div class="card-body">
			<div class="card-text prispevek-content" style="white-space:pre-wrap !important"><?=$p->content?></div>
			<? if (RCViewController::isUserLogged()) { ?>
				<? if (isUserAdmin() || $_SESSION["id"] == $p->user_id) { ?>
					<div style="display:none" class="container edit-prispevek-form">
						<form method="post" action="<?=$config["baseUrl"]?>/forum/<?=urlencode($_GET["ck_nazev"])?>">
							<input type="hidden" name="prispevek_id" value="<?=$p->id?>">
							<textarea class="form-control" name="content"><?=$p->content?></textarea><br />
							<button name="action" value="edit" class="btn btn-primary">Upravit příspěvek</button>
						</form>
						<br />
					</div>
				<? } ?>
				<hr />
				<div class="text-center">
					<? if (RCViewController::isUserAdmin() || $_SESSION["id"] == $p->user_id) { ?>
						<form method="post" action="<?=$config["baseUrl"]?>/forum/<?=urlencode($_GET["ck_nazev"])?>" class="d-inline">
							<input type="hidden" name="prispevek_id" value="<?=$p->id?>">
							<button name="action" value="delete" class="btn btn-danger" onclick="return confirm('Skutečně smazat příspěvek?');"><i class="fa fa-trash"></i></button>
						</form>
					<? } ?>
					<? if (RCViewController::isUserAdmin() || $_SESSION["id"] == $p->user_id) { ?>
						<div class="btn btn-primary edit-button" onclick="showDiskuzeEditForm(this)"><i class="fa fa-edit"></i></div>
					<? } ?>
				</div>
			<? } ?>
		</div>
	</div>

<? } ?>