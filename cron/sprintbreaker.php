<?php
/*
 *
 *  ~SprintBreaker~
 *  Moves tasks under projects into sprints by due date
 *
 */

require_once "base.php";

$issue_type_project = $f3->get("issue_type.project");

// Get all current and future sprints
$sprint = new \Model\Sprint();
$sprints = $sprint->find(array("start_date => ? OR end_date <= ?", now()));

// Get all top level projects
$top_level_projects = new \Model\Issue();
$projects = $top_level_projects->find(array("type_id = ? AND parent_id IS NULL AND sprint_id IS NULL", $issue_type_project));

if($projects && $sprints) {
	foreach($projects as $project) {

		// Get all tasks with due dates directly under project
		$due_date_tasks = new \Model\Issue();
		$tasks = $due_date_tasks->find(array(
			"parent_id = :project AND due_date > :now AND type_id != :type",
			":project" => $project->id,
			":now" => now(),
			":type" => $issue_type_project
		));

		if($tasks) {
			foreach($sprints as $sprint) {

				$start = strtotime($sprint->start_date);
				$end = strtotime($sprint->end_date);
				$tasks_for_this_sprint = array();

				// Find tasks that fit into this sprint
				foreach($tasks as &$task) {
					$due = strtotime($task->due_date);
					if($due >= $start_date && $due <= $end_date) {
						$tasks_for_this_sprint[] = &$task;
					}
				}

				// Create sprint project
				if(count($tasks_for_this_sprint)) {

					$sprint_project = new \Model\Issue();
					$sprint_project->parent_id = $project->id;
					$sprint_project->sprint_id = $sprint->id;
					$sprint_project->name = "Sprint Project";
					$sprint_project->save();

					// Move tasks into sprint project
					foreach($tasks_for_this_sprint as &$task) {
						$task->parent_id = $sprint_project->id;
						$task->save();
					}

				}
			}
		}

	}
}
