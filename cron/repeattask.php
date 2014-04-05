<?php //USED TO GENERATE REPEATABLE TASKS AND PUT THEM INTO SPRINTS IF APPLICABLE

require_once "base.php";

//RECURSIVE FUNCTION TO MOVE A TASK AND ALL CHILDREN
function cp_issue($issue, $parent_id = NULL) {
    $repeat_issue = new \Model\Issue();
    $repeat_issue->name = $issue->name;
    $repeat_issue->type_id = $issue->type_id;
    $repeat_issue->parent_id = $parent_id; // ASSIGN BY VALUE PASSED FROM NEW ISSUE
    $repeat_issue->sprint_id = $issue->sprint_id;
    $repeat_issue->author_id = $issue->author_id;
    $repeat_issue->owner_id = $issue->owner_id;
    $repeat_issue->description = $issue->description;
    $repeat_issue->repeat_cycle = $issue->repeat_cycle;

    //FIND A DUE DATE IN THE FUTURE
    if($issue->repeat_cycle == 'weekly') {
        $dow = date("l", strtotime($issue->due_date));
        $repeat_issue->due_date = date("Y-m-d", strtotime("Next {$dow}"));

    } else if($issue->repeat_cycle == 'monthly') {
        $day = date("d", strtotime($issue->due_date));
        $month = date("m");
        $year = date("Y");
        $repeat_issue->due_date = date("Y-m-d", mktime(0,0,0, $month +1, $day, $year));

    } else if($issue->repeat_cycle == 'sprint') {
        $sprint = new \Model\Sprint();
        $sprint->load(array("start_date > NOW()"), array('order'=>'start_date'));
        $repeat_issue->due_date =  $sprint->end_date;
    }

    // IF THE PROJECT WAS IN A SPRINT BEFORE, PUT IT IN A SPRINT AGAIN
    if(!empty($issue->sprint_id )) {
        $sprint = new \Model\Sprint();
        $sprint->load(array("end_date < $repeat_issue->due_date"), array('order'=>'start_date'));
        $repeat_issue->sprint_id = $sprint->id;
    }

    $repeat_issue->created_date = now();
    $repeat_issue->save();
    $child_issue = new \Model\Issue();
    $child_issue->load(array('parent_id = ?', $parent_id));

    while ( !$child_issue->dry() ) {
        $new_child = cp_issue($child_issue, $repeat_issue->id);
        $child_issue->repeat_cycle = 'none';
        $child_issue->save();
        $child_issue->next();
    }

    return $repeat_issue;
}

$issue = new \Model\Issue();

    $issue->load(array('repeat_cycle != "none"
     AND (closed_date IS NOT NULL
        OR closed_date > "0000-00-00 00:00:00")
     AND (deleted_date IS NULL
        OR deleted_date = "0000-00-00 00:00:00")
     AND (parent_id IS NULL
     OR parent_id = 0
     OR parent_id = "")
    '));

while ( !$issue->dry() ) {
	echo $issue->id ." ". $issue->name . "\r\n";
        //MAKE A NEW TASK - ONLY FOR PARENT = 0 TASKS
        $repeat_issue = cp_issue($issue);
        echo $repeat_issue->id ." ". $repeat_issue->name . "\r\n";

        //SET OLD TASK TO NOT REPEAT
        $issue->repeat_cycle = 'none';
    $issue->save();
	$issue->next();
}


