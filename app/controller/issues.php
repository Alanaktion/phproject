<?php

namespace Controller;

class Issues extends Base {

	public function index($f3, $params) {
		$this->_requireLogin();

		$issues = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_detail", null, 3600);

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
			$filter_str .= "$i = '$val' AND ";
		}
		$filter_str .= "deleted_date IS NULL";

		// Load type if a type_id was passed
		if(!empty($args["type"])) {
			$type = new \Model\Issue\Type();
			$type->load($args["type"]);
			if($type->id) {
				$f3->set("title", $type->name . "s");
				$f3->set("type", $type->cast());
			}
		}

		$f3->set("issues", $issues->paginate(0, 50, $filter_str));
		echo \Template::instance()->render("issues/index.html");
	}

	public function add($f3, $params) {
		$this->_requireLogin();

		if($f3->get("PARAMS.type")) {
			$type_id = $f3->get("PARAMS.type");
		} else {
			$type_id = 1;
		}

		$type = new \Model\Issue\Type();
		$type->load(array("id=?", $type_id));

		if(!$type->id) {
			$f3->error(404, "Issue type does not exist");
			return;
		}

		$status = new \Model\Issue\Status();
		$f3->set("statuses", $status->paginate(0, 100));

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
        $f3->set("groups", $users->paginate(0, 1000, "deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$f3->set("title", "New " . $type->name);
		$f3->set("type", $type->cast());

		echo \Template::instance()->render("issues/edit.html");
	}

	public function add_selecttype($f3, $params) {
		$this->_requireLogin();

		$type = new \Model\Issue\Type();
		$f3->set("types", $type->paginate(0, 50));

		echo \Template::instance()->render("issues/new.html");
	}

	public function edit($f3, $params) {
		$this->_requireLogin();

		$issue = new \Model\Issue();
		$issue->load(array("id=?", $f3->get("PARAMS.id")));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$type = new \Model\Issue\Type();
		$type->load(array("id=?", $issue->type_id));

		$status = new \Model\Issue\Status();
		$f3->set("statuses", $status->paginate(0, 100));

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
        $f3->set("groups", $users->paginate(0, 1000, "deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$f3->set("title", "Edit #" . $issue->id);
		$f3->set("issue", $issue->cast());
		$f3->set("type", $type->cast());

		echo \Template::instance()->render("issues/edit.html");
	}

	public function save($f3, $params) {
		$user_id = $this->_requireLogin();

		$post = array_map("trim", $f3->get("POST"));

		$issue = new \Model\Issue();
		if(!empty($post["id"])) {

			// Updating existing issue.
			$issue->load($post["id"]);
			if($issue->id) {

				// Log changes
				$update = new \Model\Issue\Update();
				$update->issue_id = $issue->id;
				$update->user_id = $user_id;
				$update->created_date = now();
				$update->save();

				// Diff contents and save what's changed.
				foreach($post as $i=>$val) {
					if($i != "notify" && $issue->$i != $val) {
						$update_field = new \Model\Issue\Update\Field();
						$update_field->issue_update_id = $update->id;
						$update_field->field = $i;
						$update_field->old_value = $issue->$i;
						$update_field->new_value = $val;
						$update_field->save();
						if(empty($val)) {
							$issue->$i = null;
						} else {
							$issue->$i = $val;
							if($i == "status") {
								$status = new \Model\Issue\Status();
								$status->load($val);
								if($status->closed) {
									$issue->date_closed = now();
								}
							}
						}
					}
				}

				// Save issue
				$issue->save();

				if($f3->get("user.role") == "admin" && empty($post["notify"])) {
					// Don't send notification
				} else {
					// Notify watchers
					$notification = \Helper\Notification::instance();
					$notification->issue_update($issue->id, $update->id);
				}

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
			$issue->owner_id = $post["owner_id"];
			if(!empty($post["due_date"])) {
				$issue->due_date = date("Y-m-d", strtotime($post["due_date"]));
			}
			if(!empty($post["parent_id"])) {
				$issue->parent_id = $post["parent_id"];
			}
			$issue->save();

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
		$user_id = $this->_requireLogin();

		$issue = new \Model\Issue();
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
					$comment->user_id = $user_id;
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
								"text" => $comment->text,
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

		$f3->set("title", $issue->name);

		$author = new \Model\User();
		$author->load(array("id=?", $issue->author_id));
		$owner = new \Model\User();
		$owner->load(array("id=?", $issue->owner_id));

		$status = new \Model\Issue\Status();
		$status->load($issue->status);
		$f3->set("status", $status->cast());

		$files = new \Model\Issue\File();
		$f3->set("files", $files->paginate(0, 64, array("issue_id = ?", $issue->id)));

		$watching = new \Model\Issue\Watcher();
		$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $user_id));
		$f3->set("watching", !!$watching->id);

		$f3->set("issue", $issue->cast());
		$f3->set("hierarchy", $issue->hierarchy());
		$f3->set("type", $type->cast());
		$f3->set("author", $author->cast());
		$f3->set("owner", $owner->cast());

		$comments = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_comment_user", null, 3600);
		$f3->set("comments", $comments->paginate(0, 100, array("issue_id = ?", $issue->id), array("order" => "created_date ASC")));

		echo \Template::instance()->render("issues/single.html");
	}

	public function single_history($f3, $params) {
		$user_id = $this->_requireLogin();

		// Build updates array
		$updates_array = array();
		$update_model = new \Model\Custom("issue_update_user");
		$updates = $update_model->paginate(0, 100, array("issue_id = ?", $params["id"]), array("order" => "created_date ASC"));
		foreach($updates["subset"] as $update) {
			$update_array = $update->cast();
			$update_field_model = new \Model\Issue\Update\Field();
			$update_array["changes"] = $update_field_model->paginate(0, 100, array("issue_update_id = ?", $update["id"]));
			$updates_array[] = $update_array;
		}

		$f3->set("updates", $updates_array);

		echo \Template::instance()->render("issues/single/history.html");
	}

	public function single_related($f3, $params) {
		$user_id = $this->_requireLogin();
		$issue = new \Model\Issue();
		$issue->load($params["id"]);

		if($issue->id) {
			$issues = new \Model\Custom("issue_detail");
			if($f3->get("issue_type.project") == $issue->type_id) {
				$f3->set("issues", $issues->paginate(0, 100, array("parent_id = ? AND deleted_date IS NULL", $issue->id)));
			} else {
				$f3->set("issues", $issues->paginate(0, 100, array("parent_id = ? AND parent_id IS NOT NULL AND parent_id <> 0 AND deleted_date IS NULL AND id <> ?", $issue->parent_id, $issue->id)));
			}
			echo \Template::instance()->render("issues/single/related.html");
		} else {
			$f3->error(404);
		}
	}

	public function single_watchers($f3, $params) {
		$user_id = $this->_requireLogin();
		$watchers = new \Model\Custom("issue_watcher_user");
		$f3->set("watchers", $watchers->paginate(0, 100, array("issue_id = ?", $params["id"])));
		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 100, "deleted_date IS NULL AND role != 'group'"));
		echo \Template::instance()->render("issues/single/watchers.html");
	}

	public function single_delete($f3, $params) {
		$this->_requireLogin();

		$issue = new \Model\Issue();
		$issue->load($params["id"]);
		if($f3->get("POST.id")) {
			$issue->delete();
			$f3->reroute("/issues");
		} else {
			$f3->set("issue", $issue->cast());
			echo \Template::instance()->render("issues/delete.html");
		}
	}

	public function search($f3, $params) {
		$query = "%" . $f3->get("GET.q") . "%";
		$issues = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_detail", null, 3600);
		$results = $issues->paginate(0, 50, array("name LIKE ? OR description LIKE ? AND deleted_date IS NULL", $query, $query), array("order" => "created_date ASC"));
		$f3->set("issues", $results);
		echo \Template::instance()->render("issues/search.html");
	}

	public function upload($f3, $params) {
		$user_id = $this->_requireLogin();
		$issue = new \Model\Issue();
		$issue->load(array("id=? AND deleted_date IS NULL", $f3->get("POST.issue_id")));
		if(!$issue->id) {
			$f3->error(404);
			return;
		}

		$f3->set("issue", $issue->cast());

		$web = \Web::instance();


		$f3->set("UPLOADS",'uploads/'.date("Y")."/".date("m")."/"); // don't forget to set an Upload directory, and make it writable!
		if(!is_dir($f3->get("UPLOADS"))) {
			mkdir($f3->get("UPLOADS"), 0777, true);
		}
		$overwrite = false; // set to true, to overwrite an existing file; Default: false
		$slug = true; // rename file to filesystem-friendly version

		//Make a good name
		$f3->set("orig_name", preg_replace("/[^A-Z0-9._-]/i", "_", $_FILES['attachment']['name']));
		$_FILES['attachment']['name'] = time() . "_" . $f3->get("orig_name");

		$i = 0;
		$parts = pathinfo($_FILES['attachment']['name']);
		while (file_exists($f3->get("UPLOADS") . $_FILES['attachment']['name'])) {
			$i++;
			$_FILES['attachment']['name'] = $parts["filename"] . "-" . $i . "." . $parts["extension"];
		}

		$files = $web->receive(function($file){
			$f3 = \Base::instance();

			//var_dump($file);
			/* looks like:
				array(5) {
					["name"] =>     string(19) "somefile.png"
					["type"] =>     string(9) "image/png"
					["tmp_name"] => string(14) "/tmp/php2YS85Q"
					["error"] =>    int(0)
					["size"] =>     int(172245)
				}
			*/
			// $file['name'] already contains the slugged name now

			// maybe you want to check the file size
			if($file['size'] > $f3->get("files.maxsize"))
				return false; // this file is not valid, return false will skip moving it


			$newfile = new \Model\Issue\File();
			$newfile->issue_id = $f3->get("issue.id");
			$newfile->user_id = $f3->get("user.id");
			$newfile->filename = $f3->get("orig_name");
			$newfile->disk_filename = $file['name'];
			$newfile->disk_directory = $f3->get("UPLOADS");
			$newfile->filesize = $file['size'];
			$newfile->content_type = $file['type'];
			$newfile->digest = md5_file($file['tmp_name']); // Need to MD5 the tmp file, since the final one doesn't exist yet.
			$newfile->created_date = now();
			$newfile->save();

			// TODO: Add history entry to see who uploaded which files and when

			// everything went fine, hurray!
			return true; // allows the file to be moved from php tmp dir to your defined upload dir
		},
			$overwrite,
			$slug
		);

		$f3->reroute("/issues/" . $issue->id);

	}

}
