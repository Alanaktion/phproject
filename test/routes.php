<?php
/**
 * Tests all basic routes
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

require_once "base.php";

// Set up basic web environment
$f3->mset(array(
	"microtime" => microtime(true),
	"site.url" => $f3->get("SCHEME") . "://" . $f3->get("HOST") . $f3->get("BASE") . "/",
	"revision" => ""
));

$f3->config($homedir."app/routes.ini");
$f3->config($homedir."app/dict/en.ini");

$test = new Test;

// No output for routes
$f3->set("QUIET", true);

$f3->mock("GET /login");
$test->expect(
	!$f3->get("ERROR"),
	"GET /login"
);

$f3->mock("POST /login", array("username" => "admin", "password" => "admin"));
$test->expect(
	!$f3->get("ERROR"),
	"POST /login"
);

// Enable output again
$f3->set("QUIET", false);

// Output results
showResults($test);
