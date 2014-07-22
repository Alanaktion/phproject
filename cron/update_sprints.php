<?php
/**
 *  update_sprints.php
 *  Resets sprint IDs on child tasks
 */

require_once "base.php";

$issues = \Model\Issue;
$issues->find(array("type_id = ? AND deleted_date IS NULL", $f3->get("issue_type.project")));

foreach($isses as $issue) {
	$issue->resetChildren();
}
