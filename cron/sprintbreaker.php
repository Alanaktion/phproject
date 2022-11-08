<?php

/**
 * ~SprintBreaker~
 * Alanaktion's insane attempt at automated sprint management.
 *
 * Moves tasks under projects into sprints by due date
 *
 * Tested, but still a bit scary. Also unnecessary now that
 * sprint tasks are dynamically loaded by due date.
 *
 * Use with caution and pizza.
 *
 **/

require_once "base.php";

$issue_type_project = $f3->get("issue_type.project");

// Get all current and future sprints
$sprint = new \Model\Sprint();
$sprints = $sprint->find(["start_date >= :now OR end_date <= :now", ":now" => date("Y-m-d")]);
echo "Using " . count($sprints) . " sprints.\n";

// Get all top level projects
$project_model = new \Model\Issue();
$projects = $project_model->find(["type_id = ? AND parent_id IS NULL AND sprint_id IS NULL", $issue_type_project]);

if ($projects && $sprints) {
    foreach ($projects as $project) {
        echo "\nBreaking project {$project->id}:\n";

        // Get all tasks with due dates directly under project
        $due_date_tasks = new \Model\Issue();
        $tasks = $due_date_tasks->find([
            "parent_id = :project AND due_date >= :now AND type_id != :type",
            ":project" => $project->id,
            ":now" => date("Y-m-d"),
            ":type" => $issue_type_project,
        ]);

        if ($tasks) {
            foreach ($sprints as $sprint) {
                echo "Using sprint {$sprint->id}\n";

                $start = strtotime($sprint->start_date);
                $end = strtotime($sprint->end_date);
                $tasks_for_this_sprint = [];

                // Find tasks that fit into this sprint
                foreach ($tasks as $task) {
                    echo "Using task {$task->id}\n";
                    $due = strtotime($task->due_date);
                    if ($due >= $start && $due <= $end) {
                        echo "Task marked for move {$task->id}\n";
                        $tasks_for_this_sprint[] = $task;
                    }
                }

                // Create sprint project
                if (count($tasks_for_this_sprint)) {
                    echo "Creating project for sprint {$sprint->id}\n";
                    $sprint_project = new \Model\Issue();
                    $sprint_project->type_id = $issue_type_project;
                    $sprint_project->parent_id = $project->id;
                    $sprint_project->sprint_id = $sprint->id;
                    $sprint_project->author_id = $project->author_id;
                    $sprint_project->owner_id = $project->owner_id;
                    if ($sprint->name) {
                        $sprint_project->name = $project->name . " - " . $sprint->name . " - " . date("n/j", strtotime($sprint->start_date)) . "-" . date("n/j", strtotime($sprint->start_date));
                    } else {
                        $sprint_project->name = $project->name . " - " . date("n/j", strtotime($sprint->start_date)) . "-" . date("n/j", strtotime($sprint->start_date));
                    }
                    $sprint_project->description = "This is an automatically generated project for breaking large projects into sprints.";
                    $sprint_project->created_date = date("Y-m-d H:i:s");
                    $sprint_project->save();

                    // Move tasks into sprint project
                    foreach ($tasks_for_this_sprint as $task) {
                        echo "Moving task {$task->id} into sprint project {$sprint_project->id}\n";
                        $task->parent_id = $sprint_project->id;
                        $task->save();
                    }
                }
            }
        } else {
            echo "No tasks found.\n";
        }
    }
}
