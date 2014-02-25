<?php //USED TO GENERATE REPEATABLE TASKS AND PUT THEM INTO SPRINTS IF APPLICABLE

require_once "base.php";
		
$issue = new \Model\Issue();
		
			$issue->load(array('repeat_cycle != "none"
			 AND (closed_date IS NOT NULL
				OR closed_date > "0000-00-00 00:00:00")
			 AND (deleted_date IS NULL
				OR deleted_date = "0000-00-00 00:00:00")
			 AND (parent_id IS NULL 
			 or parent_id <= 0)
			'));

while ( !$issue->dry() ) {  
	echo $issue->id ." ". $issue->name . "\r\n";
        //MAKE A NEW TASK
        $repeat_issue = new \Model\Issue();
        $repeat_issue->name = $issue->name;
        $repeat_issue->type_id = $issue->type_id;
        $repeat_issue->parent_id = $issue->parent_id;
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
        }
        
        
        $repeat_issue->created_date = now();
        $repeat_issue->save();
        echo $repeat_issue->id ." ". $repeat_issue->name . "\r\n";
	
        //SET OLD TASK TO NOT REPEAT
        $issue->repeat_cycle = 'none';
	$issue->next();
}


	
?>


