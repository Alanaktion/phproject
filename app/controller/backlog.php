<?php

namespace Controller;

class Backlog extends Base {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	public function index($f3, $params) {
		$sprint_model = new \Model\Sprint();
		$sprints = $sprint_model->find(array("end_date >= ?", now(false)), array("order" => "start_date ASC"));

		$issue = new \Model\Issue\Detail();

		$sprint_details = array();
		foreach($sprints as $sprint) {
			$projects = $issue->find(array("deleted_date IS NULL AND sprint_id = ? AND type_id = ?", $sprint->id, $f3->get("issue_type.project")));
			$sprint_details[] = $sprint->cast() + array("projects" => $projects);
		}

		$large_projects = $f3->get("db.instance")->exec("SELECT parent_id FROM issue WHERE parent_id IS NOT NULL AND type_id = ?", $f3->get("issue_type.project"));
		$large_project_ids = array();
		foreach($large_projects as $p) {
			$large_project_ids[] = $p["parent_id"];
		}
		if(!empty($large_project_ids)) {
			$large_project_ids = implode(",", $large_project_ids);
			$unset_projects = $issue->find(array("deleted_date IS NULL AND sprint_id IS NULL AND type_id = ? AND closed_date IS NULL AND id NOT IN ({$large_project_ids})", $f3->get("issue_type.project")));
		} else {
			$unset_projects = array();
		}

		$f3->set("sprints", $sprint_details);
		$f3->set("backlog", $unset_projects);

		$f3->set("title", "Backlog");
		$f3->set("menuitem", "backlog");
		echo \Template::instance()->render("backlog/index.html");
	}

	public function edit($f3, $params) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["itemId"]);
		$issue->sprint_id = empty($post["reciever"]["receiverId"]) ? null : $post["reciever"]["receiverId"];
		$issue->save();
		print_json($issue);
	}

}
