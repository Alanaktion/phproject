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

$f3->route("GET /issues", function($f3) {
	if($f3->get("user.id") || $f3->get("site.public")) {
		$issues = new DB\SQL\Mapper($f3->get("db.instance"), "issues_user_data");

		// Filter issue listing by URL parameters
		$filter = array();
		$args = $f3->get("GET");
		if(!empty($args["type"])) {
			$filter["type_id"] = intval($args["type"]);
		}
		if(isset($args["owner"])) {
			$filter["owner_id"] = intval($args["owner"]);
		}

		// Build SQL string to use for filtering
		$filter_str = "";
		foreach($filter as $i => $val) {
			$filter_str .= "$i = '$val' and ";
		}
		$filter_str = substr($filter_str, 0, strlen($filter_str) - 5); // Remove trailing "and "

		// Load type if a type_id was passed
		if(!empty($args["type"])) {
			$type = new Model\Issue\Type();
			$type->load(array("id = ?", $args["type"]));
			if($type->id) {
				$f3->set("title", $type->name . "s");
				$f3->set("type", $type->cast());
			}
		}

		$f3->set("issues", $issues->paginate(0, 50, $filter_str));
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

		$users = new Model\User();
		$f3->set("users", $users->paginate(0, 1000, null, array("order" => "name ASC")));

		$f3->set("title", "New " . $type->name);
		$f3->set("type", $type->cast());

		echo Template::instance()->render("issues/edit.html");
	} else {
		$f3->error(403, "Authentication Required");
	}
});

$f3->route("POST /issues/save", function($f3) {
	if($f3->get("user.id") && $f3->get("POST.name")) {
		$issue = new Model\Issue();
		$issue->author_id = $f3->get("user.id");
		$issue->type_id = $f3->get("POST.type_id");
		$issue->created_date = date("Y-m-d H:i:s");
		$issue->name = $f3->get("POST.name");
		$issue->description = $f3->get("POST.description");
		$issue->owner_id = $f3->get("POST.owner_id");
		$issue->due_date = date("Y-m-d", strtotime($f3->get("POST.due_date")));
		$issue->parent_id = $f3->get("POST.parent_id");
		$issue->save();
		if($issue->id) {
			$f3->reroute("/issues/" . $issue->id);
		} else {
			$f3->error(500, "An error occurred saving the issue.");
		}
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

		$f3->set("title", $issue->name);

		$author = new Model\User();
		$author->load(array("id=?", $issue->author_id));

		$f3->set("issue", $issue->cast());
		$f3->set("author", $author->cast());

		echo Template::instance()->render("issue.html");
	} else {
		$f3->error(403, "Authentication Required");
	}
});


$f3->route("GET /checkmail", function($f3) {
	// IN PROGRESS!!!
	// NEED TO RUN THIS ONLY AS A CRON
	
	if( TRUE ){ // $f3->get("cronjob") ) {
		/* connect to gmail */
		$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
		$username = 'shelf@alanaktion.net';
		$password = 'shelfy123';
		
		$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());

		$emails = imap_search($inbox,'ALL UNSEEN');

		if($emails) {
			echo "found mail";
			
			/* put the newest emails on top */
			rsort($emails);
			
			/* for every email... */
			foreach($emails as $email_number) {
				
				/* get information specific to this email */
				$overview = imap_fetch_overview($inbox,$email_number,0);
				$message = imap_fetchbody($inbox,$email_number,2);
				
				$issue = new Model\Issue();
				
				$issue->name = $overview[0]->subject;
				$issue->description = $message;
				$issue->save();
				
				
			}
			
			
		} 
		
		echo "all done again";

		/* close the connection */							
		imap_close($inbox);										
	} else {
		$f3->error(403, "Internal Authentication Required");
	}
		

});

