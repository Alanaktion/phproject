<?php

namespace Controller;

class Taskboard extends Base {

	public function index($f3, $params) {
        $this->_requireLogin();

        // Load the requested sprint
        $sprint = new \Model\Sprint();
        $sprint->load($params["id"]);
        if(!$sprint->id) {
            $f3->error(404);
            return;
        }
        $f3->set("sprint", $sprint->cast());
        $f3->set("title", $sprint->name);

        // Load issue statuses
        $status = new \Model\Issue\Status();
        $statuses = $status->paginate(0, 100);
        $f3->set("statuses", $statuses);

        // Load project list
        $issue = new \Model\Custom("issue_user");
        $projects = $issue->paginate(0, 100, array("sprint_id = ? AND deleted_date IS NULL", $sprint->id), array("order" => "owner_id ASC"));

        // Build multidimensional array of all tasks and projects
        $taskboard = array();
        foreach($projects["subset"] as $project) {

            // Build array of statuses to put tasks under
            $columns = array();
            foreach($statuses["subset"] as $status) {
                $columns[$status["id"]] = array();
            }

            // Get all tasks under the project, put them under their status
            $tasks = $issue->paginate(0, 100, array("parent_id = ? AND deleted_Date IS NULL", $project["id"]), array("order" => "due_date ASC"));
            foreach($tasks["subset"] as $task) {
                $columns[$task["status"]][] = $task;
            }

            // Add hierarchial structure to taskboard array
            $taskboard[] = array(
                "project" => $project,
                "columns" => $columns
            );

        }
        $f3->set("taskboard", $taskboard);

        // Get user list for select
        $users = new \Model\User();
        $f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL", array("order" => "name ASC")));

		echo \Template::instance()->render("taskboard/index.html");

	}

    public function add($f3, $params) {

    }

    public function edit($f3, $params) {
        $post = $f3->get("POST");
        $issue = new \Model\Issue();
        $issue->load($post["taskId"]);
        $issue->parent_id = $post["receiver"]["story"];
        $issue->status = $post["receiver"]["status"];
        $issue->save();
        echo json_encode($issue->cast());
    }




    public function test($f3) {
        $this->_requireLogin();
        echo \Template::instance()->render("taskboard/test.html");
    }


}
