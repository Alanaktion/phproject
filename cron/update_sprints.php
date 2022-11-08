<?php

/**
 *  update_sprints.php
 *  Resets sprint IDs on child tasks
 */

require_once "base.php";

$issues = new \Model\Issue();
$issues->find(["type_id = ? AND deleted_date IS NULL", $f3->get("issue_type.project")]);

foreach ($issues as $issue) {
    $issue->resetChildren(false);
}
