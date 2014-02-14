<?php
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

while ( !$issue->dry() ) {  // gets dry when we passed the last record
	echo $issue->name;
	// moves forward even when the internal pointer is on last record
	$issue->next();
}


	
?>


