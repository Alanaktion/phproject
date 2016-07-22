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
	"ESCAPE" => false,
	"TZ" => "UTC",
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
 * @param   Test $test
 */
function showResults(Test $test) {
	$err = false;
	$f3 = Base::instance();

	if(PHP_SAPI == 'cli') { // Command line

		foreach($test->results() as $result) {
			if ($result['status']) {
				if(defined('PHP_WINDOWS_VERSION_MAJOR'))
					echo "PASS";
				else
					echo "\033[0;32m", 'PASS', "\033[0m";
				echo ": ", $result['text'], "\n";
			} else {
				if(defined('PHP_WINDOWS_VERSION_MAJOR'))
					echo "FAIL";
				else
					echo "\033[0;31m", 'FAIL', "\033[0m";
				echo ": ", $result['text'], " - ", $result['source'], "\n";
				$err = true;
			}
		}

		if($err) {
			echo "One or more tests failed. Last error:\n";
			echo $f3->get("ERROR.text"), " at " . $f3->get("ERROR.trace.0.file"), ":", $f3->get("ERROR.trace.0.line"), "\n";
			register_shutdown_function(function() {
				exit(2);
			});
		}

	} else { // Web page

		foreach($test->results() as $result) {
			if ($result['status']) {
				echo '<code style="color: darkgreen;">PASS</code>: ', $result['text'], "<br>\n";
			} else {
				echo '<code style="color: red;">FAIL</code>: ', $result['text'], " - ", $result["source"], "<br>\n";
				$err = true;
			}
		}

		if($err) {
			echo "<p>One or more tests failed. Last error:<br>\n";
			echo $f3->get("ERROR.text"), " at " . $f3->get("ERROR.trace.0.file"), ":", $f3->get("ERROR.trace.0.line"), "</p>\n";

			if($f3->get("DEBUG") >= 3) {
				foreach($f3->get("ERROR.trace") as $line) {
					echo "<b>", $line["file"], "</b><br>";
					echo $line["line"], ": ", $line["class"], $line["type"], $line["function"], "(", implode(", ", $line["args"]), ")<br>";
				}
			}

			register_shutdown_function(function() {
				exit(2);
			});
		}
	}
}
