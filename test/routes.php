<?php
/**
 * Tests all basic routes
 * @package  Test
 * @author   Alan Hardman <alan@phpizza.com>
 */

require_once "base.php";

// Set up basic web environment
$f3->mset(array(
	"site.url" => $f3->get("SCHEME") . "://" .
		$f3->get("HOST") . $f3->get("BASE") . "/",
	"revision" => ""
));

$f3->config($homedir."app/routes.ini");
$f3->config($homedir."app/dict/en.ini");

$test = new Test;

// No output for routes
$f3->set("QUIET", true);
$f3->set("HALT", false);

$f3->mock("GET /login");
$test->expect(
	!$f3->get("ERROR"),
	"GET /login"
);

$f3->mock("POST /login", array("username" => "admin",
	"password" => "admin"));

$test->expect(
	!$f3->get("ERROR"),
	"POST /login"
);

$f3->mock("GET /ping");
$test->expect(
	!$f3->get("ERROR"),
	"GET /ping (no session)"
);

// Build a fake session
$user = new Model\User;
$user->load(1);
$types = new \Model\Issue\Type;
$f3->mset(array(
	"user" => $user->cast(),
	"user_obj" => $user,
	"plugins" => array(),
	"issue_types" => $types->find()
));

$test->expect(
	$user->id == 1,
	"Force user authentication"
);

$f3->mock("GET /ping");
$test->expect(
	!$f3->get("ERROR"),
	"GET /ping (active session)"
);

$f3->mock("GET /");
$test->expect(
	!$f3->get("ERROR"),
	"GET /"
);

$f3->mock("GET /issues/1");
$test->expect(
	$f3->get("PARAMS.id") == 1 && !$f3->get("ERROR"),
	"GET /issues/1"
);

$f3->mock("GET /issues/1/history");
$test->expect(
	$f3->get("PARAMS.id") == 1 && !$f3->get("ERROR"),
	"GET /issues/1/history"
);

$f3->mock("GET /issues/1/watchers");
$test->expect(
	$f3->get("PARAMS.id") == 1 && !$f3->get("ERROR"),
	"GET /issues/1/watchers"
);

$f3->mock("GET /issues/1/related");
$test->expect(
	$f3->get("PARAMS.id") == 1 && !$f3->get("ERROR"),
	"GET /issues/1/related"
);

$f3->mock("GET /backlog");
$test->expect(
	!$f3->get("ERROR"),
	"GET /backlog"
);

$f3->mock("GET /user");
$test->expect(
	!$f3->get("ERROR"),
	"GET /user"
);

// Enable output again
$f3->set("QUIET", false);

// Output results
showResults($test);
