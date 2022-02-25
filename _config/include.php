<?php

function createMenuItem($title, $path) {
	$btn = "btn-primary";
	if (strcmp($path, "/" . $_GET["subview"]) == 0) {
        $btn = "btn-success";
    }
?>
	<button class="btn <?=$btn?>" style="margin-top:5px" onclick="location.href = '/admin<?=$path?>';"><?=$title?></button>
	<?
}

function printRequestLog($log) {

    if (empty($log)) {
        return;
    }

    if (!empty($log->warnings)) {
		 ?><div class="alert alert-warning" role="alert"><span class="h4 alert-heading">Oznámení</span><br /><br />
			<ul>
				<? foreach ($log->warnings as $warning) { ?>
					<li><?=$warning?></li>
				<? } ?>
			</ul>
		</div><br /><?
	}
	if (!empty($log->errors)) {
		?><div class="alert alert-danger" role="alert"><span class="h4 alert-heading">Chyba</span>><br /><br />
			<ul>
				<? foreach ($log->errors as $error) { ?>
					<li><?=$error?></li>
				<? } ?>
			</ul>
		</div><br /><?
	}
	if (!empty($log->logs) || !empty($log->success)) {

		$logs = (empty($log->logs) ? $log->success : $log->logs)
		 ?><div class="alert alert-success" role="alert"><span class="h4 alert-heading">Úspěch</span><br /><br />
			<ul>
				<? foreach ($logs as $log) { ?>
					<li><?=$log?></li>
				<? } ?>
			</ul>
		</div><br/><?
	}
}

function strip_tags_ex($string) {
	$string = strip_tags($string);

	if (empty($string)) {
        return null;
    }

    return $string;
}
	
function generateSafeMail($email)
{
	$pos = strpos($email, '@');

	$user = substr($email, 0, $pos);

	$domain = substr($email, $pos + 1, strlen($email));


	for ($i = 1; $i < strlen($user); ++$i) {
        $user[$i] = '*';
    }

    $toggle = false;
	for ($i = strlen($domain) - 1; $i >= 1 ; --$i) {

            if ($toggle) {
            $domain[$i] = '*';
        }

        if (!$toggle && $domain[$i] == '.') {
            $toggle = true;
        }
    }

	return $user . "@" . $domain;
}

function trimContent($str,$trimAtIndex) {

    $beginTags = array();       
    $endTags = array();

    for($i = 0; $i < strlen($str); $i++) {
        if ($str[$i] == '<') {
            $beginTags[] = $i;
        } else if ($str[$i] == '>') {
            $endTags[] = $i;
        }
    }

    foreach($beginTags as $k=>$index) {
        // Trying to trim in between tags. Trim after the last tag
        if( ( $trimAtIndex >= $index ) && ($trimAtIndex <= $endTags[$k])  ) {
            $trimAtIndex = $endTags[$k];
        }
    }

    return substr($str, 0, $trimAtIndex);
}