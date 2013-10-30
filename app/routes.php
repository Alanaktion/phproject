<?php

// Define app routes

$f3->route("GET /", function($f3) {
	if($f3->get("user.id")) {
		$projects = new DB\SQL\Mapper($f3->get("db.instance"), "issues_user_data");
		$f3->set("projects", $projects->paginate(
			0, 50,
			array(
				"owner_id=:owner and type_id=:type",
				":owner" => $f3->get("user.id"),
				":type" => "2",
			),array(
				"order" => "(due_date IS NULL), due_date ASC"
			)
		));

		$tasks = new DB\SQL\Mapper($f3->get("db.instance"), "issues_user_data");
		$f3->set("tasks", $tasks->paginate(
			0, 50,
			array(
				"owner_id=:owner and type_id=:type",
				":owner" => $f3->get("user.id"),
				":type" => "1",
			),array(
				"order" => "(due_date IS NULL), due_date ASC"
			)
		));

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

$f3->route("GET /issues", function($f3, $args) {
	if($f3->get("user.id") || $f3->get("site.public")) {
		echo Template::instance()->render("issues.html");
	} else {
		$f3->error(403, "Authentication Required");
	}
});

$f3->route("GET /issues/new", function($f3) {
	$f3->reroute("/issues/new/1");
});

$f3->route("GET /issues/new/@type", function($f3) {
	if($f3->get("user.id") || $f3->get("site.public")) {
		$type = new Model\Issue\Type();
		$type->load(array("id=?", $f3->get("PARAMS.type")));

		if(!$type->id) {
			$f3->error(500, "Issue type does not exist");
			return;
		}

		$f3->set("type", $type->cast());

		echo Template::instance()->render("issues/new.html");
	} else {
		$f3->error(403, "Authentication Required");
	}
});

$f3->route("GET /issues/@id", function($f3) {
	if($f3->get("user.id") || $f3->get("site.public")) {
		$issue = new Model\Issue();
		$issue->load(array("id=?", $f3->get("PARAMS.id")));

		if(!$issue->id) {
			$f3->error(404);
			return;
		}

		$author = new Model\User();
		$author->load(array("id=?", $issue->author_id));

		$f3->set("issue", $issue->cast());
		$f3->set("author", $author->cast());

		echo Template::instance()->render("issue.html");
	} else {
		$f3->error(403, "Authentication Required");
	}
});


