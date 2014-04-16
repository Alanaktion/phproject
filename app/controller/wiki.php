<?php

namespace Controller;

class Wiki extends Base {

	public function index($f3) {
		$this->_requireLogin();

		$page = new \Model\Wiki\Page;
		$page->load(array("slug = ?", "index"));

		if($page->id) {
			$f3->reroute("/wiki/{$page->slug}");
		} else {
			$page->name = "Index";
			$page->slug = "index";
			$page->created = now();
			$page->save();
			$f3->reroute("/wiki/edit/{$page->id}");
		}
	}

	public function view($f3, $params) {
		$this->_requireLogin();

		$page = new \Model\Wiki\Page;
		$page->load(array("slug = ?", $params["slug"]));

		if($page->id) {
			$f3->set("page", $page);
			echo \Template::instance()->render("wiki/page/view.html");
		} else {
			$f3->reroute("/wiki/edit");
		}
	}

	public function edit($f3, $params) {
		$user_id = $this->_requireLogin();

		// Save page on POST request
		if($post = $f3->get("POST")) {
			$page = new \Model\Wiki\Page;

			// Load existing page if an ID is specified
			if($f3->get("POST.id")) {
				$page->load($params["id"]);
			}

			// Set page slug if page does not exist and no slug was passed
			if(!$page->id && !$f3->get("POST.slug")) {
				$page->slug = Web::instance()->slug($f3->get("POST.name"));
			} elseif(!$page->id) {
				$page->slug = $f3->get("POST.slug");
			}

			// Update page name and content with POSTed data
			$page->name = $f3->get("POST.name");
			$old_content = $page->content;
			$page->content = $f3->get("POST.content");

			// Save the wiki page
			$page->created = now();
			$page->save();

			// Log the page update
			$update = new \Model\Wiki\Page\Update;
			$update->wiki_page_id = $page->id;
			$update->user_id = $user_id;
			$update->old_content = $old_content;
			$update->new_content = $page->content;
			$update->save();

			// Redirect to the page
			$f3->reroute("/wiki/{$page->slug}");
			return;
		}

		// Show wiki page editor
		$page = new \Model\Wiki\Page;

		// Load existing page if an ID is specified
		if(!empty($params["id"])) {
			$page->load($params["id"]);
		}

		// List all pages for parent selection
		$f3->set("pages", $page->find());

		$f3->set("page", $page);
		echo \Template::instance()->render("wiki/page/edit.html");
	}

}
