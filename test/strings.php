<?php
/**
 * Tests basic string manipulation and parsing, primarily used in views
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

require_once "base.php";
$test = new Test;

$inflector = Helper\Inflector::instance();
$test->expect(
	$inflector->pluralize("task") == "tasks",
	"Inflector->pluralize()"
);

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

$f3->set("site.theme", "css/bootstrap-phproject.css");
$test->expect(
	$view->parseTextile("Hi :) _codez_") == '<p>Hi <span class="emote'
		. ' emote-smiley"></span> <em>codez</em></p>',
	"View->parseTextile()"
);

$test->expect(
	$view->make_clickable("Test http://www.phproject.org/")
		== 'Test <a href="http://www.phproject.org/" rel="nofollow"'
			. ' target="_blank">http://www.phproject.org/</a>',
	"View->make_clickable()"
);



date_default_timezone_set("Etc/UTC");
$f3->set("site.timezone", "America/Denver");
$test->expect(
	$view->utc2local(1420498500) == 1420473300,
	"View->utc2local()"
);

// Output results
showResults($test);
