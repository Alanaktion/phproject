<?php
/**
 * Run all tests at once, outputting all results
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

if(PHP_SAPI != 'cli') { ?>
<!DOCTYPE html>
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="initial-scale=1, minimum-scale=1, width=device-width">
<title>Unit Test Results</title>
<h1>Unit Test Results</h1>
<?php }

showHeader("Issues");
include "issues.php";

showHeader("Strings");
include "strings.php";
