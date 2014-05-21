<?php

namespace Controller;

class Issues extends Base {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	public function index($f3, $params) {
		$issues = new \Model\Issue\Detail();

		// Filter issue listing by URL parameters
		$filter = array();
		$args = $f3->get("GET");
		foreach($args as $key=>$val) {
			if(!empty($val) && $issues->exists($key)) {
				$filter[$key] = $val;
			}
		}

		// Build SQL string to use for filtering
		$filter_str = "";
		foreach($filter as $i => $val) {
			if($i == "name") {
				$filter_str .= "`$i` LIKE '%" . addslashes($val) . "%' AND ";
			} elseif($i == "status" && $val == "open") {
				$filter_str .= "closed_date IS NULL AND ";
			} elseif($i == "status" && $val == "closed") {
				$filter_str .= "closed_date IS NOT NULL AND ";
			} else {
				$filter_str .= "`$i` = '" . addslashes($val) . "' AND ";
			}
		}
		$filter_str .= "deleted_date IS NULL";

		// Load type if a type_id was passed
		$type = new \Model\Issue\Type();
		if(!empty($args["type_id"])) {
			$type->load($args["type_id"]);
			if($type->id) {
				$f3->set("title", $type->name . "s");
				$f3->set("type", $type);
			}
		} else {
			$f3->set("title", "Issues");
		}

		$status = new \Model\Issue\Status();
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		if(empty($args["page"])) {
			$args["page"] = 0;
		}
		$issue_page = $issues->paginate($args["page"], 50, $filter_str);
		$f3->set("issues", $issue_page);

		// Set up pagination
		$filter_get = http_build_query($filter);
		if($issue_page["pos"] < $issue_page["count"] - 1) {
			$f3->set("next", "?page=" . ($issue_page["pos"] + 1) . "&" . $filter_get);
		}
		if($issue_page["pos"] > 0) {
			$f3->set("prev", "?page=" . ($issue_page["pos"] - 1) . "&" . $filter_get);
		}

		$f3->set("show_filters", true);
		$f3->set("menuitem", "browse");
		echo \Template::instance()->render("issues/index.html");
	}

	public function add($f3, $params) {
		if($f3->get("PARAMS.type")) {
			$type_id = $f3->get("PARAMS.type");
		} else {
			$type_id = 1;
		}

		$type = new \Model\Issue\Type();
		$type->load($type_id);

		if(!$type->id) {
			$f3->error(404, "Issue type does not exist");
			return;
		}

		if($f3->get("PARAMS.parent")) {
			$parent = $f3->get("PARAMS.parent");
			$parent_issue = new \Model\Issue();
			$parent_issue->load(array("id=? AND (closed_date IS NULL OR closed_date = '0000-00-00 00:00:00')", $parent));
			if($parent_issue->id){
				$f3->set("parent", $parent);
			}
		}

		$status = new \Model\Issue\Status();
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint();
		$f3->set("sprints", $sprint->find(array("end_date >= ?", now(false)), array("order" => "start_date ASC")));

		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$f3->set("title", "New " . $type->name);
		$f3->set("menuitem", "new");
		$f3->set("type", $type);

		echo \Template::instance()->render("issues/edit.html");
	}

	public function add_selecttype($f3, $params) {
		$type = new \Model\Issue\Type();
		$f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

		$f3->set("title", "New Issue");
		$f3->set("menuitem", "new");
		echo \Template::instance()->render("issues/new.html");
	}

	public function edit($f3, $params) {
		$issue = new \Model\Issue();
		$issue->load($f3->get("PARAMS.id"));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$type = new \Model\Issue\Type();
		$type->load($issue->type_id);

		$status = new \Model\Issue\Status();
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint();
		$f3->set("sprints", $sprint->find(array("end_date >= ?", now(false)), array("order" => "start_date ASC")));

		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$f3->set("title", "Edit #" . $issue->id);
		$f3->set("issue", $issue);
		$f3->set("type", $type);

		if($f3->get("AJAX")) {
			echo \Template::instance()->render("issues/edit-form.html");
		} else {
			echo \Template::instance()->render("issues/edit.html");
		}
	}

	public function close($f3, $params){
		$issue = new \Model\Issue();
		$issue->load($f3->get("PARAMS.id"));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$status = new \model\issue\status;
		$status->load(array("closed = ?", 1));
		$issue->status = $status->id;
		$issue->closed_date = now();
		$issue->save();

		$f3->reroute("/issues/" . $issue->id);
	}

	public function save($f3, $params) {
		$post = array_map("trim", $f3->get("POST"));

		$issue = new \Model\Issue();
		if(!empty($post["id"])) {

			// Updating existing issue.
			$issue->load($post["id"]);
			if($issue->id) {

				// Diff contents and save what's changed.
				foreach($post as $i=>$val) {
					if($i != "notify" && $issue->$i != $val && $issue->exists($i)) {
						if(empty($val)) {
							$issue->$i = null;
						} else {
							$issue->$i = $val;
							if($i == "status") {
								$status = new \Model\Issue\Status();
								$status->load($val);

								// Toggle closed_date if issue has been closed/restored
								if($status->closed) {
									if(!$issue->closed_date) {
										$issue->closed_date = now();
									}
								} else {
									$issue->closed_date = null;
								}
							}

							// Save to the sprint of the due date
							if ($i=="due_date" && !empty($val)) {
								$sprint = new \Model\Sprint();
								$sprint->load(array("DATE(?) BETWEEN start_date AND end_date",$val));
								// $sprint->load("id=9");
								$issue->sprint_id = $sprint->id;
							}
						}
					}
				}
				// Save issue, send notifications (unless admin opts out)
				$notify =  empty($post["notify"]) ? false : true;
				$issue->save($notify);

				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(404, "This issue does not exist.");
			}

		} elseif($f3->get("POST.name")) {

			// Creating new issue.
			$issue->author_id = $f3->get("user.id");
			$issue->type_id = $post["type_id"];
			$issue->created_date = now();
			$issue->name = $post["name"];
			$issue->description = $post["description"];
			$issue->priority = $post["priority"];
			$issue->status = $post["status"];
			$issue->owner_id = $post["owner_id"];
			$issue->hours_total = $post["hours_remaining"];
			$issue->hours_remaining = $post["hours_remaining"];
			$issue->repeat_cycle = $post["repeat_cycle"];

			if(!empty($post["due_date"])) {
				$issue->due_date = date("Y-m-d", strtotime($post["due_date"]));

				//Save to the sprint of the due date
				$sprint = new \Model\Sprint();
				$sprint->load(array("DATE(?) BETWEEN start_date AND end_date",$issue->due_date));
				$issue->sprint_id = $sprint->id;
			}
			if(!empty($post["parent_id"])) {
				$issue->parent_id = $post["parent_id"];
			}

			// Save issue, send notifications (unless admin opts out)
			$notify =  empty($post["notify"]) ? false : true;
			$issue->save($notify);

			if($issue->id) {
				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(500, "An error occurred saving the issue.");
			}

		} else {
			$f3->reroute("/issues/new/" . $post["type_id"]);
		}
	}

	public function single($f3, $params) {
		$issue = new \Model\Issue\Detail();
		$issue->load(array("id=? AND deleted_date IS NULL", $f3->get("PARAMS.id")));

				if(!$issue->id) {
			$f3->error(404);
			return;
		}

		$type = new \Model\Issue\Type();
		$type->load($issue->type_id);

		// Run actions if passed
		$post = $f3->get("POST");
		if(!empty($post)) {
			switch($post["action"]) {
				case "comment":
					$comment = new \Model\Issue\Comment();
					$comment->user_id = $this->_userId;
					$comment->issue_id = $issue->id;
					$comment->text = $post["text"];
					$comment->created_date = now();
					$comment->save();

					$notification = \Helper\Notification::instance();
					$notification->issue_comment($issue->id, $comment->id);

					if($f3->get("AJAX")) {
						print_json(
							array(
								"id" => $comment->id,
								"text" => parseTextile($comment->text),
								"date_formatted" => date("D, M j, Y \\a\\t g:ia"),
								"user_name" => $f3->get('user.name'),
								"user_username" => $f3->get('user.username'),
								"user_email" => $f3->get('user.email'),
								"user_email_md5" => md5(strtolower($f3->get('user.email'))),
							)
						);
						return;
					}
					break;

				case "add_watcher":
					$watching = new \Model\Issue\Watcher();
					// Loads just in case the user is already a watcher
					$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $post["user_id"]));
					$watching->issue_id = $issue->id;
					$watching->user_id = $post["user_id"];
					$watching->save();

					if($f3->get("AJAX"))
						return;
					break;

				case "remove_watcher":
					$watching = new \Model\Issue\Watcher();
					$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $post["user_id"]));
					$watching->delete();

					if($f3->get("AJAX"))
						return;
					break;
			}
		}

		$f3->set("title", $type->name . " #" . $issue->id  . ": " . $issue->name);
		$f3->set("menuitem", "browse");

		$author = new \Model\User();
		$author->load($issue->author_id);
		$owner = new \Model\User();
		$owner->load($issue->owner_id);

		$files = new \Model\Issue\File\Detail();
		$f3->set("files", $files->find(array("issue_id = ? AND deleted_date IS NULL", $issue->id)));

		if($issue->sprint_id) {
			$sprint = new \Model\Sprint();
			$sprint->load($issue->sprint_id);
			$f3->set("sprint", $sprint);
		}

		$watching = new \Model\Issue\Watcher();
		$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $this->_userId));
		$f3->set("watching", !!$watching->id);

		$f3->set("issue", $issue);
		$f3->set("hierarchy", $issue->hierarchy());
		$f3->set("type", $type);
		$f3->set("author", $author);
		$f3->set("owner", $owner);

		// Extra data needed for inline edit form
		$status = new \Model\Issue\Status();
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority();
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint();
		$f3->set("sprints", $sprint->find(array("end_date >= ?", now(false)), array("order" => "start_date ASC")));

		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$comments = new \Model\Issue\Comment\Detail;
		$f3->set("comments", $comments->find(array("issue_id = ?", $issue->id), array("order" => "created_date ASC")));

		echo \Template::instance()->render("issues/single.html");

	}

	public function single_history($f3, $params) {
		// Build updates array
		$updates_array = array();
		$update_model = new \Model\Custom("issue_update_user");
		$updates = $update_model->find(array("issue_id = ?", $params["id"]), array("order" => "created_date DESC"));
		foreach($updates as $update) {
			$update_array = $update->cast();
			$update_field_model = new \Model\Issue\Update\Field();
			$update_array["changes"] = $update_field_model->find(array("issue_update_id = ?", $update["id"]));
			$updates_array[] = $update_array;
		}

		$f3->set("updates", $updates_array);

		print_json(array(
			"total" => count($updates),
			"html" => \Template::instance()->render("issues/single/history.html")
		));
	}

	public function single_related($f3, $params) {
		$issue = new \Model\Issue();
		$issue->load($params["id"]);

		if($issue->id) {
			$issues = new \Model\Issue\Detail();
			if($f3->get("issue_type.project") == $issue->type_id) {
				$f3->set("issues", $issues->find(array("parent_id = ? AND deleted_date IS NULL", $issue->id)));
				$f3->set("parent", $issue);
			} else {
				$f3->set("issues", $issues->find(array("parent_id = ? AND parent_id IS NOT NULL AND parent_id <> 0 AND deleted_date IS NULL AND id <> ?", $issue->parent_id, $issue->id)));
				$parent = new \Model\Issue();
				$parent->load($issue->parent_id);
				$f3->set("parent", $parent);
			}

			print_json(array(
				"total" => count($f3->get("issues")),
				"html" => \Template::instance()->render("issues/single/related.html")
			));
		} else {
			$f3->error(404);
		}
	}

	public function single_watchers($f3, $params) {
		$watchers = new \Model\Custom("issue_watcher_user");
		$f3->set("watchers", $watchers->find(array("issue_id = ?", $params["id"])));
		$users = new \Model\User();
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));

		print_json(array(
			"total" => count($f3->get("watchers")),
			"html" => \Template::instance()->render("issues/single/watchers.html")
		));
	}

	public function single_delete($f3, $params) {
		$issue = new \Model\Issue();
		$issue->load($params["id"]);
		$issue->delete();
		$f3->reroute("/issues?deleted={$issue->id}");

		/* Old delete with confirmation
		$issue = new \Model\Issue();
		$issue->load($params["id"]);
		if($f3->get("POST.id")) {
			$issue->delete();
			$f3->reroute("/issues");
		} else {
			$f3->set("issue", $issue);
			echo \Template::instance()->render("issues/delete.html");
		}*/
	}

	public function single_undelete($f3, $params) {
		$issue = new \Model\Issue();
		$issue->load($params["id"]);
		$issue->deleted_date = null;
		$issue->save();
		$f3->reroute("/issues/{$issue->id}");
	}

	public function file_delete($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($f3->get("POST.id"));
		$file->delete();
		print_json($file->cast());
	}

	public function file_undelete($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($f3->get("POST.id"));
		$file->deleted_date = null;
		$file->save();
		print_json($file->cast());
	}

	public function search($f3, $params) {
		$query = "%" . $f3->get("GET.q") . "%";
		$issues = new \Model\Issue\Detail();

		$args = $f3->get("GET");
		if(empty($args["page"])) {
			$args["page"] = 0;
		}

		$where = "(id = ? OR name LIKE ? OR description LIKE ?
				OR author_name LIKE ? OR owner_name LIKE ?
				OR author_username LIKE ? OR owner_username LIKE ?
				OR author_email LIKE ? OR owner_email LIKE ?)
			AND deleted_date IS NULL";
		$issue_page = $issues->paginate($args["page"], 50, array($where, $f3->get("GET.q"), $query, $query, $query, $query, $query, $query, $query, $query), array("order" => "created_date DESC"));
		$f3->set("issues", $issue_page);

		$f3->set("show_filters", false);
		echo \Template::instance()->render("issues/search.html");
	}

	public function upload($f3, $params) {
		$user_id = $this->_userId;

		$issue = new \Model\Issue();
		$issue->load(array("id=? AND deleted_date IS NULL", $f3->get("POST.issue_id")));
		if(!$issue->id) {
			$f3->error(404);
			return;
		}

		$web = \Web::instance();

		$f3->set("UPLOADS",'uploads/'.date("Y")."/".date("m")."/"); // don't forget to set an Upload directory, and make it writable!
		if(!is_dir($f3->get("UPLOADS"))) {
			mkdir($f3->get("UPLOADS"), 0777, true);
		}
		$overwrite = false; // set to true to overwrite an existing file; Default: false
		$slug = true; // rename file to filesystem-friendly version

		// Make a good name
		$orig_name = preg_replace("/[^A-Z0-9._-]/i", "_", $_FILES['attachment']['name']);
		$_FILES['attachment']['name'] = time() . "_" . $orig_name;

		$i = 0;
		$parts = pathinfo($_FILES['attachment']['name']);
		while (file_exists($f3->get("UPLOADS") . $_FILES['attachment']['name'])) {
			$i++;
			$_FILES['attachment']['name'] = $parts["filename"] . "-" . $i . "." . $parts["extension"];
		}

		$files = $web->receive(
			function($file) use($f3, $orig_name, $user_id, $issue) {

				if($file['size'] > $f3->get("files.maxsize"))
					return false;

				$newfile = new \Model\Issue\File();
				$newfile->issue_id = $issue->id;
				$newfile->user_id = $user_id;
				$newfile->filename = $orig_name;
				$newfile->disk_filename = $file['name'];
				$newfile->disk_directory = $f3->get("UPLOADS");
				$newfile->filesize = $file['size'];
				$newfile->content_type = $file['type'];
				$newfile->digest = md5_file($file['tmp_name']);
				$newfile->created_date = now();
				$newfile->save();
				$f3->set('file_id', $newfile->id);

				return true; // moves file from php tmp dir to upload dir
			},
			$overwrite,
			$slug
		);

		if($f3->get("POST.text")) {
			$comment = new \Model\Issue\Comment();
			$comment->user_id = $this->_userId;
			$comment->issue_id = $issue->id;
			$comment->text = $f3->get("POST.text");
			$comment->created_date = now();
			$comment->file_id = $f3->get('file_id');
			$comment->save();

			$notification = \Helper\Notification::instance();
			$notification->issue_comment($issue->id, $comment->id);
		}

		$f3->reroute("/issues/" . $issue->id);
	}

	// Quick add button for adding tasks to projects
	// TODO: Update code to work with frontend outside of taskboard
	public function quickadd($f3, $params) {
		$post = $f3->get("POST");

		$issue = new \Model\Issue();
		$issue->name = $post["title"];
		$issue->description = $post["description"];
		$issue->author_id = $this->_userId;
		$issue->owner_id = $post["assigned"];
		$issue->created_date = now();
		$issue->hours_total = $post["hours"];
		if(!empty($post["dueDate"])) {
			$issue->due_date = date("Y-m-d", strtotime($post["dueDate"]));
		}
		$issue->priority = $post["priority"];
		$issue->parent_id = $post["storyId"];
		$issue->save();

		print_json($issue->cast() + array("taskId" => $issue->id));
	}
}
