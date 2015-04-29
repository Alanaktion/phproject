<?php

namespace Controller;

class Tag extends \Controller {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireLogin(0);
	}

	/**
	 * Tag index route (/tag/)
	 * @param \Base $f3
	 */
	public function index($f3) {
		$tag = new \Model\Issue\Tag;
		$cloud = $tag->cloud();
		shuffle($cloud);
		$f3->set("title", $f3->get("dict.issue_tags"));
		$f3->set("cloud", $cloud);
		$this->_render("tag/index.html");
	}

	/**
	 * Single tag route (/tag/@tag)
	 * @param \Base $f3
	 * @param array $params
	 */
	public function single($f3, $params) {
		$tag = new \Model\Issue\Tag;
		$tag->load(array("tag = ?", $params["tag"]));

		if(!$tag->id) {
			$f3->error(404);
			return;
		}

		$issue = new \Model\Issue\Detail;
		$issue_ids = implode(',', $tag->issues());

		$f3->set("title", "#" . $params["tag"] . " - " . $f3->get("dict.issue_tags"));
		$f3->set("tag", $tag);
		$f3->set("issues.subset", $issue->find("id IN ($issue_ids)"));
		$this->_render("tag/single.html");
	}

}
