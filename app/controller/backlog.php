<?php

namespace Controller;

class Backlog extends Base {

	public function index($f3, $params) {
		$this->_requireLogin();

		$sprint_model = new \Model\Sprint();
		$sprints = $sprint_model->paginate(0, 100, array("end_date > ?", now(false)), array("order" => "start_date ASC"));

		$issue = new \Model\Issue();

		$sprint_details = array();
		foreach($sprints["subset"] as $sprint) {
			$projects = $issue->paginate(0, 100, array("deleted_date IS NULL AND sprint_id = ? AND type_id = ?", $sprint->id, $f3->get("issue_type.project")));
			$sprint_details[] = $sprint->cast() + array("projects" => $projects["subset"]);
		}

		$unset_projects = $issue->paginate(0, 1000, array("deleted_date IS NULL AND sprint_id IS NULL AND type_id = ?", $f3->get("issue_type.project")));

		$f3->set("sprints", $sprint_details);
		$f3->set("backlog", $unset_projects);

		echo \Template::instance()->render("backlog/index.html");
	}

	public function edit($f3, $params) {
		$post = $f3->get("POST");
		$issue = new \Model\Issue();
		$issue->load($post["itemId"]);
		$issue->sprint_id = empty($post["reciever"]["receiverId"]) ? null : $post["reciever"]["receiverId"];
		$issue->save();
		print_json($issue->cast());
	}


}
