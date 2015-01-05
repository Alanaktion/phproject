<?php
/**
 * Run ALL tests at once, outputting all results
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

/**
 * Show a header formatted for CLI or web
 * @param  string $title
 */
function showHeader($title) {
	if(PHP_SAPI == 'cli') {
		echo "\n--- $title ---\n";
	} else {
		echo "<h2>$title</h2>\n";
	}
}

if(PHP_SAPI != 'cli') {
	echo "<h1>Unit Test Results</h1>\n";
}

showHeader("Issues");
include "issues.php";

showHeader("Strings");
include "strings.php";

showHeader("Routes");
include "routes.php";
