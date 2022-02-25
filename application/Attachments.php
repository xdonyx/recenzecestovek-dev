<?php

class Attachments {

	private $maxAttachments = 2;

	private $attachments;

	private $allowedTypes = [
		"gif" => "image/gif",
		"jpg" => "image/jpeg",
		"png" => "image/png",
		"webp" => "image/webp",
	];

	public function __construct() {
		$this->attachments = Array();
	}

	private function getUniqueName($path, $suffix) {
		$file = null;
		do {
			$file = $path . mt_rand() . $suffix;
			$fp = @fopen($file, "x");
		} while(!$fp);

		fclose($fp);
		return $file;
	}

	public function get() {
		return $this->attachments;
	}

	public function upload() {
		global $config;

		$log = new Logger();

		for ($i = 0; $i < $this->maxAttachments; ++$i)
		{
			if (!empty($_FILES["obrazky"]["name"][$i]))
			{
				$format = "\"" . htmlspecialchars($_FILES["obrazky"]["name"][$i]) . "\": ";

				// Basic error check
				if (!isset($_FILES["obrazky"]["error"][$i]) || is_array($_FILES["obrazky"]["error"][$i]))
				{
					$log->error("Neplatná požadavka");
					continue;
				}

				$error = $this->fileError($format . $_FILES["obrazky"]["error"][$i]);

				if (!empty($error))
				{
					$log->error($error);
					continue;
				}

				$filePath = $_FILES["obrazky"]["tmp_name"][$i];
				$fileSize = filesize($filePath);
   				$finfo = new finfo(FILEINFO_MIME_TYPE);

   				// empty
				if ($fileSize === 0)
				{
					$log->error($format . " je prázdný soubor");
					continue;
				}

				if ($_FILES["obrazky"]["size"][$i]/1024/1000 > (int) ini_get("upload_max_filesize"))
				{
					$log->error($format . " Soubor překročil omezení velikosti");
					continue;
				}

				// file type
				$ext = array_search($finfo->file($filePath), $this->allowedTypes, true);

				if ($ext === false)
				{
					$log->error($format . " Povolené typy souborů: " . implode(", ", $this->allowedTypes));
					continue;
				}

				// generate random name with $ext in path
				$this->attachments[$i] = $this->getUniqueName($config["uploadPath"], "." . $ext);

				// upload
				if (move_uploaded_file($_FILES["obrazky"]["tmp_name"][$i], $this->attachments[$i]))
				{
					chmod($this->attachments[$i], 644);
				}
				else
				{
					$this->attachments[$i] = null;
					$log->error("Chyba při nahrávání souboru");
				}
			}
		}

		return $log;
	}

	public function delete() {
		global $config;

		for ($i = 0; $i < $this->maxAttachments; ++$i)
		{
			if (!empty($this->attachments[$i]))
			{
				unlink($this->attachments[$i]);
			}
		}
	}

	private function fileError($error) {
		$message = "";

		switch($error)
		{
			case UPLOAD_ERR_OK:
			case UPLOAD_ERR_NO_FILE:
				break;
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				$message = "Soubor překročil omezení velikosti";
				break;
			default:
				$message = "Chyba při nahrávání souboru";
		}

		return $message; 
	}
}