<?php

// Define app routes

$f3->route("GET /", function($f3) {
	if($f3->get("user.id")) {
		echo Template::instance()->render("dashboard.html");
	} else {
		echo Template::instance()->render("index.html");
	}
});

$f3->route("GET /login", function($f3) {
	if($f3->get("user.id")) {
		$f3->reroute("/");
	} else {
		echo Template::instance()->render("login.html");
	}
});

$f3->route("GET /logout", function($f3) {
	$f3->clear("SESSION.user_id");
	$f3->reroute("/");
});

$f3->route("POST /login", function($f3) {
	$user = new Model\User();
	$user->load(array("username=?",$f3->get("POST.username")));

	if($user->verify_password($f3->get("POST.password"))) {
		$f3->set("SESSION.user_id", $user->id);
		$f3->reroute("/");
	} else {
		$f3->set("login.error", "Invalid login information, try again.");
		echo Template::instance()->render("login.html");
	}
});

$f3->route("GET /login", function($f3) {
	if($f3->get("user.id")) {
		$f3->reroute("/");
	} else {
		echo Template::instance()->render("login.html");
	}
});

/*$f3->route("GET /task/@task_id", function($f3) {
	if($f3->get("user.id") || $f3->get("site.public")) {
		echo Template::instance()->render("task.html");
	} else {
		$f3->error(403, "Authentication Required");
	}
});*/


