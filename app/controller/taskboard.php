<?php

namespace Controller;

class Taskboard extends Base {

	public function index($f3, $params) {

        // Load the requested sprint
        $sprint = new \Model\Sprint();
        $sprint->load($params["id"]);
        if(!$sprint->id) {
            $f3->error(404);
            return;
        }
        $f3->set("sprint", $sprint->cast());

        // Load issue statuses
        $status = new \Model\Issue();
        $statuses = $status->paginate(0, 20);
        $f3->set("statuses", $statuses);

        // Load project list
        $issue = new \Model\Issue();
        $projects = $issue->paginate(0, 100, array("sprint_id = ? AND deleted_date IS NULL", $sprint->id), array("order" => "owner_id ASC"));

        // Build multidimensional array of all tasks and projects
        $taskboard = array();
        foreach($projects["subset"] as $project) {

            // Get all tasks under the project, put them under their status
            $tasks = $issue->paginate(0, 100, array("parent_id = ? AND deleted_Date IS NULL", $project["id"]), array("order" => "due_date ASC"));
            $columns = array();
            foreach($tasks["subset"] as $task) {
                $columns[$task["status"]] = $task;
            }

            // Add hierarchial structure to taskboard array
            $taskboard[] = array(
                "project" => $project,
                "columns" => $columns
            );

        }

        $f3->set("taskboard", $taskboard);

		echo \Template::instance()->render("taskboard/index.html");

	}

    public function test($f3) {
        echo \Template::instance()->render("taskboard/test.html");
    }


}
