<?php

namespace Controller;

class Admin extends Base {

	public function index($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Administration");

		if($f3->get("POST.action") == "clearcache") {
			\Cache::instance()->reset();
			$f3->set("success", "Cache cleared successfully.");
		}

		// Gather some stats
		$db = $f3->get("db.instance");

		$db->exec("SELECT id FROM user WHERE deleted_date IS NULL");
		$f3->set("count_user", $db->count());
		$db->exec("SELECT id FROM issue WHERE deleted_date IS NULL");
		$f3->set("count_issue", $db->count());
		$db->exec("SELECT id FROM issue_update");
		$f3->set("count_issue_update", $db->count());
		$db->exec("SELECT id FROM issue_comment");
		$f3->set("count_issue_comment", $db->count());

		if($f3->get("CACHE") == "apc") {
			$f3->set("apc_stats", apc_cache_info("user", true));
		}

		$f3->set("db_stats", $db->exec("SHOW STATUS WHERE
Variable_name LIKE 'Delayed_%' OR
Variable_name LIKE 'Table_lock%' OR
-- Variable_name = 'Connections' OR
-- Variable_name = 'Queries' OR
Variable_name = 'Uptime'"));

		echo \Template::instance()->render("admin/index.html");
	}

	public function users($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Manage Users");
		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL"));
		echo \Template::instance()->render("admin/users.html");
	}

	public function user_edit($f3, $params) {
		$this->_requireAdmin();
		$f3->set("title", "Administration");

		$user = new \Model\User();
		$user->load($params["id"]);

		if($user->id) {
			$f3->set("title", "Edit User");
			if($f3->get("POST")) {
				foreach($f3->get("POST") as $i=>$val) {
					if($i == "password" && !empty($val)) {
						$security = \Helper\Security::instance();
						$user->salt = $security->salt();
						$user->password = $security->hash($val, $user->salt);
					} elseif($user->$i != $val) {
						$user->$i = $val;
					}
					$user->save();
					$f3->set("success", "User changes saved.");
				}
			}
			$f3->set("this_user", $user->cast());
			echo \Template::instance()->render("admin/users/edit.html");
		} else {
			$f3->error(404, "User does not exist.");
		}

	}

	public function user_new($f3, $params) {
		$this->_requireAdmin();
		if($f3->get("POST")) {
			$user = new \Model\User();
			$user->username = $f3->get("POST.username");
			$user->email = $f3->get("POST.email");
			$user->name = $f3->get("POST.name");
			$security = \Helper\Security::instance();
			$user->salt = $security->salt();
			$user->password = $security->hash($f3->get("POST.password"), $user->salt);
			$user->role = $f3->get("POST.role");
			$user->task_color = ltrim($f3->get("POST.task_color"), "#");
			$user->created_date = now();
			$user->save();
			if($user->id) {
				$f3->reroute("/admin/users#" . $user->id);
			} else {
				$f3->error(500, "Failed to save user.");
			}
		} else {
			$f3->set("title", "Add User");
			$f3->set("rand_color", sprintf("#%06X", mt_rand(0, 0xFFFFFF)));
			echo \Template::instance()->render("admin/users/new.html");
		}
	}

	public function user_delete($f3, $params) {
		$user = new \Model\User();
		$user->load($params["id"]);
		$user->delete();
		if($f3->get("AJAX")) {
			echo json_encode(array("deleted" => 1));
		} else {
			$f3->reroute("/admin/users");
		}
	}

	public function groups($f3, $params) {
		$this->_requireAdmin();
		$group = new \Model\Group();
		$groups = $group->paginate(0, 100, "deleted_date IS NULL");
		$group_array =  array();
		foreach($groups["subset"] as $g) {
			$db = $f3->get("db.instance");
			$db->exec("SELECT id FROM group_user WHERE group_id = ?", $g["id"]);
			$count = $db->count();
			$group_array[] = array(
				"id" => $g["id"],
				"name" => $g["name"],
				"count" => $count
			);
		}
		$f3->set("groups", $group_array);
		echo \Template::instance()->render("admin/groups.html");
	}

	public function group_new($f3, $params) {
		$this->_requireAdmin();
		if($f3->get("POST")) {
			$group = new \Model\Group();
			$group->name = $f3->get("POST.name");
			$group->save();
			$f3->reroute("/admin/groups");
		} else {
			$f3->error(405);
		}
	}

	public function group_edit($f3, $params) {
		$this->_requireAdmin();

		$group = new \Model\Group();
		$group->load(array("id = ? AND deleted_date IS NULL", $params["id"]));
		$f3->set("group", $group->cast());

		$members = new \DB\SQL\Mapper($f3->get("db.instance"), "group_user_user", null, 3600);
		$f3->set("members", $members->paginate(array("group_id = ? AND deleted_date IS NULL", $group->id)));

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL", array("order" => "name ASC")));

		echo \Template::instance()->render("admin/groups/edit.html");
	}

	public function group_delete($f3, $params) {
		$group = new \Model\Group();
		$group->load($params["id"]);
		$group->delete();
		if($f3->get("AJAX")) {
			echo json_encode(array("deleted" => 1));
		} else {
			$f3->reroute("/admin/groups");
		}
	}

	public function group_ajax($f3, $params) {
		$this->_requireAdmin();

		if(!$f3->get("AJAX")) {
			$f3->error(400);
		}

		$group = new \Model\Group();
		$group->load(array("id = ? AND deleted_date IS NULL", $f3->get("POST.group_id")));

		switch($f3->get('POST.action')) {
			case "add_member":
				foreach($f3->get("POST.user") as $user_id) {
					$user = new \Model\Group\User();
					$user->load(array("user_id = ? AND group_id = ?", $user_id, $f3->get("POST.group_id")));
					if(!$user->id) {
						$user->group_id = $f3->get("POST.group_id");
						$user->user_id = $user_id;
						$user->save();
					} else {
						// user already in group
					}
				}
				break;
			case "remove_member":
				$group_user = new \Model\Group\User();
				$group_user->load(array("user_id = ? AND group_id = ?", $f3->get("POST.user_id"), $f3->get("POST.group_id")));
				$group_user->delete();
				echo json_encode(array("deleted" => 1));
				break;
			case "change_title":
				$group->name = $f3->get("POST.name");
				$group->save();
				echo json_encode(array("changed" => 1));
				break;
		}
	}

}
