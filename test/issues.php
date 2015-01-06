<?php
/**
 * Tests the Issue model's core functionality
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

require_once "base.php";
$test = new Test;

$issue = new Model\Issue;

$test->expect(
	$issue->load(1) && $issue->id == 1,
	"Issue->load() by Integer"
);

$test->expect(
	$issue->load(array('id = ?', 1)) && $issue->id == 1,
	"Issue->load() by String"
);

$test->expect(
	is_array($issue->getChildren()),
	"Issue->getChildren()"
);

$test->expect(
	is_array($issue->getAncestors()),
	"Issue->getAncestors()"
);

$test->expect(
	$issue->save(false) && $issue->id,
	"Issue->save() without notifications"
);

// Output results
showResults($test);
