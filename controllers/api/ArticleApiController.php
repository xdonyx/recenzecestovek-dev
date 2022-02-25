<?php

class ArticleApiController extends ApiController {
	
	private $db;

	private $rules = [
		[
			"type" => "string",
			"field" => "article_title",
			"pretty" => "Nadpis",
			"required" => true,
			"minLength" => 5,
		],
		[
			"type" => "string",
			"field" => "article_title_full",
			"pretty" => "Celý nadpis",
			"minLength" => 5,
		],
		[
			"type" => "string",
			"field" => "content",
			"pretty" => "Obsah článku",
			"required" => true,
			"minLength" => 10,
		],
	];

	function __construct() {
		global $config;
		parent::__construct();

		$this->RegisterRoute("/api/article.php", "get", "GET");
		$this->RegisterRoute("/api/article.php", "add", "POST");
		$this->RegisterRoute("/api/article.php", "edit", "POST");
		$this->RegisterRoute("/api/article.php", "delete", "POST");

		if (!$this->db) {
			$this->db = new Database($config["dbHost"], $config["dbUser"], $config["dbPass"], $config["dbName"]);
		}

		echo $this->Route();
	}

	function add() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$contentHTML = $request->get("content");
		$request->set("content", strip_tags($contentHTML)); // for validation
		$title  = $request->get("article_title");
		$titleFull  = $request->get("article_title_full");

		if ($titleFull == null)
			$titleFull = "";

		$validator = new Validator($request, $response->getLogger());
		if (!$validator->validate($this->rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$this->db->read("SELECT id FROM rc_clanky WHERE title = ?", "s", $title);

		if ($this->db->lastRows() > 0)
			return $response->invalidRequest("Článek s rovnakým názvem již existuje");

		$result = $this->db->write("INSERT INTO rc_clanky (title, title_full, content) VALUES (?, ?, ?)", "sss", $title, $titleFull, $contentHTML);
		
		if ($result > 0) {
			$response->getLogger()->success("Článek " . $title . " byl vytvořen (<a href=\"./" . Helpers::getValidPath($title) . "\">odkaz</a>)!");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function delete() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$articleID = $request->get("article_id");

		if (empty($articleID))
			return $response->invalidRequest();

		$result = $this->db->write("DELETE FROM rc_clanky WHERE id = ? LIMIT 1", "i", $articleID);
		
		if ($result > 0) {
			$response->getLogger()->warning("Článek smazán");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function edit() {

		$request = $this->request;
		$response = $this->response;

		if (!$request->isAuthorized())
			return $response->unauthorized();

		$articleID = $request->get("id");
		$contentHTML = $request->get("content");
		$request->set("content", strip_tags($contentHTML)); // for validation
		$title  = $request->get("article_title");
		$titleFull  = $request->get("article_title_full");

		if (empty($articleID))
			return $response->invalidRequest();

		$validator = new Validator($request, $response->getLogger());
		if (!$validator->validate($this->rules)) {
			return $response->success(RESPONSE_INCLUDE_LOG);
		}

		$this->db->read("SELECT id FROM rc_clanky WHERE id <> ? AND title = ?", "is", $articleID, $title);

		if ($this->db->lastRows() > 0)
			return $response->invalidRequest("Článek s stejným názvem již existuje");

		$result = $this->db->write("
			UPDATE rc_clanky
			SET
				title = COALESCE(?, title),
				title_full = COALESCE(?, title_full),
				content = COALESCE(?, content)
			WHERE id = ? LIMIT 1", "sssi", $title, $titleFull, $contentHTML, $articleID);
		
		if ($result > 0) {
			$response->getLogger()->success("Změny uloženy");
		} else {
			$response->getLogger()->warning("Žádné změny");
		}

		return $response->success(RESPONSE_INCLUDE_LOG);
	}

	function get() {

		$request = $this->request;

		$articleID = $request->get("id");
		
		$result = Array();
		if (!empty($articleID)) {
			$result = $this->db->read("SELECT id, title, title_full, content, edit_date FROM rc_clanky WHERE id = ? LIMIT 1", "i", $articleID);
		} else {
			$result = $this->db->read("SELECT id, title FROM rc_clanky");

			foreach ($result as &$r) {
				$r["url"] = Helpers::getValidPath($r["title"]);
			}
		}

		return json_encode($result);
	}

}