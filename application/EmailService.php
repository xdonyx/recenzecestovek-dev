<?
include ("phpmailer/PHPMailer.php");

class EmailService {

	function SendMessage($to, $subject, $message, $replyTo = null) {
		global $config;
		global $lang;
		// message body
		$finalMessage = "
		<html style=\"max-width:100%;margin:0 !important;padding:0 !important\">
			<head>
				<meta charset=\"utf-8\">
				<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
				<meta name=\"color-scheme\" content=\"light only\">
				<meta name=\"supported-color-schemes\" content=\"light only\">
			</head>
			<body style=\"max-width:100%;padding:0;margin:0;font-family:sans-serif;box-sizing:border-box\">
				<div style=\"display:block;min-width:100%;padding:1rem;background-color:rgb(92, 16, 16);\">
					<a style=\"text-shadow:-1px -1px 0 black,1px -1px 0 black,-1px 1px 0 black,1px 1px 0 black;color:white;font-size:28px;width:100%;max-height:125px;margin:0 auto;padding-left:20px;text-decoration:none;\" href=\"" . $config["baseUrl"] . "\">" . $lang["HEAD_BRAND"] . "<br /><span style=\"font-size:16px;\">" . $lang["HEAD_BRAND_MOTTO"] . "</span></a>
				</div>
				<div style=\"display:block;max-width:100%;padding:30px;background-color:transparent\">
					<h3>" . $subject . "</h3>" . $message . "
				</div>
			</body>
		</html>";

		// required headers
		$headers = "From: " . $config["contactEmail"] . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";

		// optional headers
		if ($replyTo != null) {
			$headers .= "Reply-To: " . $replyTo;
		}

		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->IsHTML(true);
		$mail->CharSet = "UTF-8";
		if (!empty($replyTo))
			$mail->AddReplyTo($replyTo, $replyTo);
		$mail->SetFrom($config["contactEmail"], $config["applicationName"]);
		$mail->Subject = $subject;
		$mail->Body = $finalMessage;
		$mail->AddAddress($to);
		$mail->Send();
	}
}