<?php
/**
 * Tests basic string manipulation and parsing, primarily used in views
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

require_once "base.php";
$test = new Test;

$security = Helper\Security::instance();
$test->expect(
	$security->rot8($security->rot8("0af")) == "0af",
	"Security->rot8()"
);

$test->expect(
	strlen($security->salt()) == 32,
	"Security->salt()"
);
$test->expect(
	strlen($security->salt_sha1()) == 40,
	"Security->salt_sha1()"
);

$string = "Hello world!";
$hash = $security->hash($string);
$test->expect(
	$security->hash($string, $hash["salt"]) == $hash["hash"],
	"Security->hash()"
);

$view = Helper\View::instance();
$test->expect(
	in_array($view->formatFilesize(1288490189), array("1.2 GB", "1.20 GB")),
	"View->formatFilesize()"
);
$test->expect(
	strpos($view->gravatar("alan@phpizza.com"),
		"gravatar.com/avatar/996df14") !== FALSE,
	"View->gravatar()"
);


$f3->set("site.timezone", "America/Phoenix");
$test->expect(
	$view->utc2local(1420498500) == 1420473300,
	"View->utc2local()"
);

// Output results
showResults($test);
