<?php
/**
 * Unit testing base initialization
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

$homedir = dirname(dirname(__FILE__)) . "/";
set_include_path($homedir);

$f3=require($homedir."lib/base.php");
$f3->mset(array(
	"CACHE" => false,
	"UI" => $homedir."app/view/",
	"LOGS" => $homedir."log/",
	"AUTOLOAD" => $homedir."app/",
	"TEMP" => $homedir."tmp/",
));

// Load local configuration
$f3->config($homedir."config-base.ini");
$f3->config($homedir."config.ini");

// Connect to database
$db = new DB\SQL(
	"mysql:host=" . $f3->get("db.host") . ";port=3306;dbname=" . $f3->get("db.name"),
	$f3->get("db.user"),
	$f3->get("db.pass")
);
$f3->set("db.instance", $db);

/**
 * Output test results formatted for CLI or web
 * @param  Test   $test
 */
function showResults(Test $test) {
	if(PHP_SAPI == 'cli') {
		foreach($test->results() as $result) {
			if ($result['status']) {
				if(defined('PHP_WINDOWS_VERSION_MAJOR')) {
					echo "PASS";
				} else {
					echo "\033[0;32m", 'PASS', "\033[0m";
				}
				echo ": ", $result['text'], "\n";
			} else {
				if(defined('PHP_WINDOWS_VERSION_MAJOR')) {
					echo "FAIL";
				} else {
					echo "\033[0;31m", 'FAIL', "\033[0m";
				}
				echo ": ", $result['text'], " - ", $result['source'], "\n";
			}
		}
	} else {
		foreach($test->results() as $result) {
			if ($result['status']) {
				echo '<code style="color: darkgreen;">PASS</code>: ', $result['text'], "<br>\n";
			} else {
				echo '<code style="color: red;">FAIL</code>: ', $result['text'], " - ", $result["source"], "<br>\n";
			}
		}
	}
}
