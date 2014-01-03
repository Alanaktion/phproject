<?php

namespace Controller;

class Issues extends Base {

	public function index($f3, $params) {
		$this->_requireLogin();

		$issues = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user", null, 3600);

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
			$type = new \Model\Issue\Type();
			$type->load(array("id = ?", $args["type"]));
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
			$f3->error(500, "Issue type does not exist");
			return;
		}

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, null, array("order" => "name ASC")));

		$f3->set("title", "New " . $type->name);
		$f3->set("type", $type->cast());

		echo \Template::instance()->render("issues/edit.html");
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

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, null, array("order" => "name ASC")));

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
			$issue->load(array("id = ?", $post["id"]));
			if($issue->id) {

				$old = array();
				$new = array();

				// Diff contents and save what's changed.
				foreach($post as $i=>$val) {
					if($issue->$i != $val) {
						$old[$i] = $issue->$i;
						$new[$i] = $val;
						$issue->$i = $val;
					}
				}
				$issue->save();

				// Log changes
				$update = new \Model\Issue\Update();
				$update->issue_id = $issue->id;
				$update->user_id = $user_id;
				$update->created_date = now();
				$update->old_data = json_encode($old);
				$update->new_data = json_encode($new);
				$update->save();

				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(500, "An error occurred saving the issue.");
			}

		} elseif($f3->get("POST.name")) {

			// Creating new issue.
			$issue->author_id = $f3->get("user.id");
			$issue->type_id = $post["type_id"];
			$issue->created_date = now();
			$issue->name = $post["type_id"];
			$issue->description = $post["type_id"];
			$issue->owner_id = $post["owner_id"];
			$issue->due_date = date("Y-m-d", strtotime($post["POST.due_date"]));
			$issue->parent_id = $f3->get("POST.parent_id");
			$issue->save();

			if($issue->id) {
				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(500, "An error occurred saving the issue.");
			}

		}
	}

	public function single($f3, $params) {
		$user_id = $this->_requireLogin();

		$issue = new \Model\Issue();
		$issue->load(array("id=?", $f3->get("PARAMS.id")));

		if(!$issue->id) {
			$f3->error(404);
			return;
		}

		// Run actions if passed
		$post = $f3->get("POST");
		if(!empty($post)) {
			switch($post["action"]) {
				case "comment":
					$comment = new \Model\Issue\Comment();
					$comment->user_id = $user_id;
					$comment->issue_id = $issue->id;
					$comment->text = $post["text"];
					$comment->created_date = date("Y-m-d H:i:s");
					$comment->save();
					if($f3->get("AJAX")) {
						echo json_encode(
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
			}
		}

		$f3->set("title", $issue->name);

		$author = new \Model\User();
		$author->load(array("id=?", $issue->author_id));

		$f3->set("issue", $issue->cast());
		$f3->set("author", $author->cast());

		$comments = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_comment_user", null, 3600);
		$f3->set("comments", $comments->paginate(0, 100, array("issue_id = ?", $issue->id), array("order" => "created_date ASC")));

		echo \Template::instance()->render("issues/single.html");
	}

}
