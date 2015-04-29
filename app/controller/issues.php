<?php

namespace Controller;

class Issues extends \Controller {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireLogin(0);
	}

	/**
	 * Clean a string for encoding in JSON
	 * Collapses whitespace, then trims
	 * @param  string $string
	 * @return string
	 */
	protected function _cleanJson($string) {
		return trim(preg_replace('/\s+/', ' ', $string));
	}

	/**
	 * Build a WHERE clause for issue listings based on the current filters and sort options
	 * @return array
	 */
	protected function _buildFilter() {
		$f3 = \Base::instance();
		$issues = new \Model\Issue\Detail;

		// Filter issue listing by URL parameters
		$filter = array();
		$args = $f3->get("GET");
		foreach($args as $key => $val) {
			if(!empty($val) && !is_array($val) && $issues->exists($key)) {
				$filter[$key] = $val;
			}
		}
		unset($val);

		// Build SQL string to use for filtering
		$filter_str = "";
		foreach($filter as $i => $val) {
			if($i == "name") {
				$filter_str .= "`$i` LIKE '%" . addslashes($val) . "%' AND ";
			} elseif($i == "status" && $val == "open") {
				$filter_str .= "status_closed = 0 AND ";
			} elseif($i == "status" && $val == "closed") {
				$filter_str .= "status_closed = 1 AND ";
			} elseif($i == "repeat_cycle" && $val == "repeat") {
				$filter_str .= "repeat_cycle NOT IN ('none', '') AND ";
			} elseif($i == "repeat_cycle" && $val == "none") {
				$filter_str .= "repeat_cycle IN ('none', '') AND ";
			} elseif(($i == "author_id" || $i== "owner_id") && !empty($val) && is_numeric($val)) {
				// Find all users in a group if necessary
				$user = new \Model\User;
				$user->load($val);
				if($user->role == 'group') {
					$group_users = new \Model\User\Group;
					$list = $group_users->find(array('group_id = ?', $val));
					$garray = array($val); // Include the group in the search
					foreach ($list as $obj) {
						$garray[] = $obj->user_id;
					}
					$filter_str .= "$i in (". implode(",",$garray) .") AND ";
				} else {
					// Just select by user
					$filter_str .= "$i = '". addslashes($val) ."' AND ";
				}
			} else {
				$filter_str .= "`$i` = '" . addslashes($val) . "' AND ";
			}
		}
		unset($val);
		$filter_str .= " deleted_date IS NULL ";

		// Build SQL ORDER BY string
		$orderby = !empty($args['orderby']) ? $args['orderby'] : "priority";
		$filter["orderby"] = $orderby;
		$ascdesc = !empty($args['ascdesc']) && $args['ascdesc'] == 'asc' ? "ASC" : "DESC";
		$filter["ascdesc"] = $ascdesc;
		switch($orderby) {
			case "id":
				$filter_str .= " ORDER BY id {$ascdesc} ";
				break;
			case "title":
				$filter_str .= " ORDER BY name {$ascdesc}";
				break;
			case "type":
				$filter_str .= " ORDER BY type_id {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "status":
				$filter_str .= " ORDER BY status {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "parent_id":
				$filter_str .= " ORDER BY parent_id {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "author":
				$filter_str .= " ORDER BY author_name {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "assignee":
				$filter_str .= " ORDER BY owner_name {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "created":
				$filter_str .= " ORDER BY created_date {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "sprint":
				$filter_str .= " ORDER BY sprint_start_date {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "closed":
				$filter_str .= " ORDER BY closed_date {$ascdesc}, priority DESC, due_date DESC ";
				break;
			case "priority":
			default:
				$filter_str .= " ORDER BY priority {$ascdesc}, due_date DESC ";
				break;
		}

		return array($filter, $filter_str);

	}

	/**
	 * Display a sortable, filterable issue list
	 * @param  Base  $f3
	 * @param  array $params
	 */
	public function index($f3, $params) {
		$issues = new \Model\Issue\Detail;

		// Get filter
		$args = $f3->get("GET");
		list($filter, $filter_str) = $this->_buildFilter();

		// Load type if a type_id was passed
		$type = new \Model\Issue\Type;
		if(!empty($args["type_id"])) {
			$type->load($args["type_id"]);
			if($type->id) {
				$f3->set("title", \Helper\Inflector::instance()->pluralize($type->name));
				$f3->set("type", $type);
			}
		} else {
			$f3->set("title", $f3->get("dict.issues"));
		}

		$status = new \Model\Issue\Status;
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority;
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint;
		$f3->set("sprints", $sprint->find(array("end_date >= ?", date("Y-m-d")), array("order" => "start_date ASC")));

		$users = new \Model\User;
		$f3->set("users", $users->getAll());
		$f3->set("groups", $users->getAllGroups());

		if(empty($args["page"])) {
			$args["page"] = 0;
		}
		$issue_page = $issues->paginate($args["page"], 50, $filter_str);
		$f3->set("issues", $issue_page);

		// Pass filter string for pagination
		$filter_get = http_build_query($filter);

		if(!empty($orderby)) {
			$filter_get  .= "&orderby=" . $orderby;
		}
		if($issue_page["count"] > 7) {
			if($issue_page["pos"] <= 3) {
				$min = 0;
			} else {
				$min = $issue_page["pos"] - 3;
			}
			if($issue_page["pos"] < $issue_page["count"] - 3) {
				$max = $issue_page["pos"] + 3;
			} else {
				$max = $issue_page["count"] - 1;
			}
		} else {
			$min = 0;
			$max = $issue_page["count"] - 1;
		}
		$f3->set("pages", range($min, $max));
		$f3->set("filter_get", $filter_get);

		$f3->set("menuitem", "browse");
		$f3->set("heading_links_enabled", true);

		$f3->set("show_filters", true);
		$f3->set("show_export", true);

		$this->_render("issues/index.html");
	}

	/**
	 * Update a list of issues
	 * @param  Base  $f3
	 * @param  array $params from form
	 */
	public function bulk_update($f3, $params) {
		$this->_requireLogin(2);
		$post = $f3->get("POST");

		$issue = new \Model\Issue;
		if( !empty($post["id"] ) && is_array($post["id"] )) {
			foreach($post["id"] as $id) {
				// Updating existing issue.
				$issue->load($id);
				if($issue->id) {

					// Diff contents and save what's changed.
					foreach($post as $i=>$val) {
						if(
							$issue->exists($i)
							&& $i != "id"
							&& $issue->$i != $val
							&& !empty($val)
						) {
							// Allow setting to Not Assigned
							if($i == "owner_id" && $val == -1) {
								$val = null;
							}
							$issue->$i = $val;
							if($i == "status") {
								$status = new \Model\Issue\Status;
								$status->load($val);

								// Toggle closed_date if issue has been closed/restored
								if($status->closed) {
									if(!$issue->closed_date) {
										$issue->closed_date = $this->now();
									}
								} else {
									$issue->closed_date = null;
								}
							}
						}
					}

					// Save to the sprint of the due date if no sprint selected
					if (!empty($post['due_date']) && empty($post['sprint_id'])) {
						$sprint = new \Model\Sprint;
						$sprint->load(array("DATE(?) BETWEEN start_date AND end_date",$issue->due_date));
						$issue->sprint_id = $sprint->id;
					}

					// If it's a child issue and the parent is in a sprint, assign to that sprint
					if(!empty($post['bulk']['parent_id']) && !$issue->sprint_id) {
						$parent = new \Model\Issue;
						$parent->load($issue->parent_id);
						if($parent->sprint_id) {
							$issue->sprint_id = $parent->sprint_id;
						}
					}

					$notify = !empty($post["notify"]);
					$issue->save($notify);

				} else {
					$f3->error(500, "Failed to update all the issues, starting with: $id.");
					return;
				}
			}

		} else {
			$f3->reroute($post["url_path"] . "?" . $post["url_query"]);
		}

		if (!empty($post["url_path"]))	{
			$f3->reroute($post["url_path"] . "?" . $post["url_query"]);
		} else {
			$f3->reroute("/issues?" . $post["url_query"]);
		}
	}

	/**
	 * Export a list of issues
	 * @param  Base  $f3
	 * @param  array $params
	 */
	public function export($f3, $params) {
		$issue = new \Model\Issue\Detail;

		// Get filter data and load issues
		$filter = $this->_buildFilter();
		$issues = $issue->find($filter[1]);

		// Configure visible fields
		$fields = array(
			"id" => $f3->get("dict.cols.id"),
			"name" => $f3->get("dict.cols.title"),
			"type_name" => $f3->get("dict.cols.type"),
			"priority_name" => $f3->get("dict.cols.priority"),
			"status_name" => $f3->get("dict.cols.status"),
			"author_name" => $f3->get("dict.cols.author"),
			"owner_name" => $f3->get("dict.cols.assignee"),
			"sprint_name" => $f3->get("dict.cols.sprint"),
			"created_date" => $f3->get("dict.cols.created"),
			"due_date" => $f3->get("dict.cols.due_date"),
		);

		// Notify browser that file is a CSV, send as attachment (force download)
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=issues-" . time() . ".csv");
		header("Pragma: no-cache");
 		header("Expires: 0");

		// Output data directly
		$fh = fopen("php://output", "w");

		// Add column headings
		fwrite($fh, '"' . implode('","', array_values($fields)) . "\"\n");

		// Add rows
		foreach($issues as $row) {
			$cols = array();
			foreach(array_keys($fields) as $field) {
				$cols[] = $row->get($field);
			}
			fputcsv($fh, $cols);
		}

		fclose($fh);
	}

	/**
	 * Export a single issue
	 * @param  Base  $f3
	 * @param  array $params
	 */
	public function export_single($f3, $params) {

	}

	/**
	 * Create a new issue
	 * @param  Base  $f3
	 * @param  array $params
	 */
	public function add($f3, $params) {
		$this->_requireLogin(2);

		if($f3->get("PARAMS.type")) {
			$type_id = $f3->get("PARAMS.type");
		} else {
			$type_id = 1;
		}

		$type = new \Model\Issue\Type;
		$type->load($type_id);

		if(!$type->id) {
			$f3->error(404, "Issue type does not exist");
			return;
		}

		if($f3->get("PARAMS.parent")) {
			$parent = $f3->get("PARAMS.parent");
			$parent_issue = new \Model\Issue;
			$parent_issue->load(array("id=? AND (closed_date IS NULL OR closed_date = '0000-00-00 00:00:00')", $parent));
			if($parent_issue->id){
				$f3->set("parent", $parent);
			}
		}

		$status = new \Model\Issue\Status;
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority;
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint;
		$f3->set("sprints", $sprint->find(array("end_date >= ?", $this->now(false)), array("order" => "start_date ASC")));

		$users = new \Model\User;
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$f3->set("title", $f3->get("dict.new_n", $type->name));
		$f3->set("menuitem", "new");
		$f3->set("type", $type);

		$this->_render("issues/edit.html");
	}

	public function add_selecttype($f3, $params) {
		$this->_requireLogin(2);

		$type = new \Model\Issue\Type;
		$f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

		$f3->set("title", $f3->get("dist.new_n", $f3->get("dict.issue")));
		$f3->set("menuitem", "new");
		$this->_render("issues/new.html");
	}

	public function edit($f3, $params) {
		$this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load($f3->get("PARAMS.id"));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$type = new \Model\Issue\Type;
		$type->load($issue->type_id);

		$status = new \Model\Issue\Status;
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority;
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint;
		$f3->set("sprints", $sprint->find(array("end_date >= ? OR id = ?", $this->now(false), $issue->sprint_id), array("order" => "start_date ASC")));

		$users = new \Model\User;
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$f3->set("title", $f3->get("edit_n", $issue->id));
		$f3->set("issue", $issue);
		$f3->set("type", $type);

		if($f3->get("AJAX")) {
			$this->_render("issues/edit-form.html");
		} else {
			$this->_render("issues/edit.html");
		}
	}

	public function close($f3, $params) {
		$this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load($f3->get("PARAMS.id"));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$status = new \Model\Issue\Status;
		$status->load(array("closed = ?", 1));
		$issue->status = $status->id;
		$issue->closed_date = $this->now();
		$issue->save();

		$f3->reroute("/issues/" . $issue->id);
	}

	public function reopen($f3, $params) {
		$this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load($f3->get("PARAMS.id"));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$status = new \Model\Issue\Status;
		$status->load(array("closed = ?", 0));
		$issue->status = $status->id;
		$issue->closed_date = null;
		$issue->save();

		$f3->reroute("/issues/" . $issue->id);
	}

	public function copy($f3, $params) {
		$this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load($f3->get("PARAMS.id"));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$new_issue = $issue->duplicate();

		if($new_issue->id) {
			$f3->reroute("/issues/" . $new_issue->id);
		} else {
			$f3->error(500, "Failed to duplicate issue.");
		}

	}

	/**
	 * Save an updated issue
	 * @return Issue
	 */
	protected function _saveUpdate() {
		$f3 = \Base::instance();
		$post = array_map("trim", $f3->get("POST"));
		$issue = new \Model\Issue;

		// Load issue and return if not set
		$issue->load($post["id"]);
		if(!$issue->id) {
			return $issue;
		}

		// Diff contents and save what's changed.
		$hashState = json_decode($post["hash_state"]);
		foreach($post as $i=>$val) {
			if(
				$issue->exists($i)
				&& $i != "id"
				&& $issue->$i != $val
				&& md5($val) != $hashState->$i
			) {
				if(empty($val)) {
					$issue->$i = null;
				} else {
					$issue->$i = $val;

					if($i == "status") {
						$status = new \Model\Issue\Status;
						$status->load($val);

						// Toggle closed_date if issue has been closed/restored
						if($status->closed) {
							if(!$issue->closed_date) {
								$issue->closed_date = $this->now();
							}
						} else {
							$issue->closed_date = null;
						}
					}

					// Save to the sprint of the due date unless one already set
					if ($i=="due_date" && !empty($val)) {
						if(empty($post['sprint_id'])) {
							$sprint = new \Model\Sprint;
							$sprint->load(array("DATE(?) BETWEEN start_date AND end_date",$val));
							$issue->sprint_id = $sprint->id;
						}
					}
				}
			}
		}

		// If it's a child issue and the parent is in a sprint,
		// use that sprint if another has not been set already
		if(!$issue->sprint_id && $issue->parent_id) {
			$parent = new \Model\Issue;
			$parent->load($issue->parent_id);
			if($parent->sprint_id) {
				$issue->sprint_id = $parent->sprint_id;
			}
		}

		// Save comment if given
		if(!empty($post["comment"])) {
			$comment = new \Model\Issue\Comment;
			$comment->user_id = $this->_userId;
			$comment->issue_id = $issue->id;
			$comment->text = $post["comment"];
			$comment->created_date = $this->now();
			$comment->save();
			$f3->set("update_comment", $comment);
		}

		// Save issue, optionally send notifications
		$notify = !empty($post["notify"]);
		$issue->save($notify);

		return $issue;
	}

	/**
	 * Save a newly created issue
	 * @return Issue
	 */
	protected function _saveNew() {
		$f3 = \Base::instance();
		$post = array_map("trim", $f3->get("POST"));
		$issue = new \Model\Issue;

		// Set all supported issue fields
		$issue->author_id = !empty($post["author_id"]) ? $post["author_id"] : $f3->get("user.id");
		$issue->type_id = $post["type_id"];
		$issue->created_date = $this->now();
		$issue->name = $post["name"];
		$issue->description = $post["description"];
		$issue->priority = $post["priority"];
		$issue->status = $post["status"];
		$issue->owner_id = $post["owner_id"] ?: null;
		$issue->hours_total = $post["hours_remaining"] ?: null;
		$issue->hours_remaining = $post["hours_remaining"] ?: null;
		$issue->repeat_cycle = in_array($post["repeat_cycle"], array("none", "")) ? null : $post["repeat_cycle"];
		$issue->sprint_id = $post["sprint_id"];

		if(!empty($post["due_date"])) {
			$issue->due_date = date("Y-m-d", strtotime($post["due_date"]));

			// Save to the sprint of the due date if a sprint was not specified
			if(!$issue->sprint_id) {
				$sprint = new \Model\Sprint();
				$sprint->load(array("DATE(?) BETWEEN start_date AND end_date",$issue->due_date));
				$issue->sprint_id = $sprint->id;
			}
		}
		if(!empty($post["parent_id"])) {
			$issue->parent_id = $post["parent_id"];
		}

		// Save issue, optionally send notifications
		$notify = !empty($post["notify"]);
		$issue->save($notify);

		return $issue;
	}

	public function save($f3, $params) {
		$this->_requireLogin(2);

		if($f3->get("POST.id")) {

			// Updating existing issue.
			$issue = $this->_saveUpdate();
			if($issue->id) {
				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(404, "This issue does not exist.");
			}

		} elseif($f3->get("POST.name")) {

			// Creating new issue.
			$issue = $this->_saveNew();
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
		$issue = new \Model\Issue\Detail;
		$issue->load(array("id=?", $f3->get("PARAMS.id")));
		$user = $f3->get("user_obj");

		if(!$issue->id || ($issue->deleted_date && !($user->role == 'admin' || $user->rank >= 3 || $issue->author_id == $user->id))) {
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
					$this->_requireLogin(1);
					$comment = new \Model\Issue\Comment;
					if(empty($post["text"])) {
						if($f3->get("AJAX")) {
							$this->_printJson(array("error" => 1));
						}
						else {
								$f3->reroute("/issues/" . $issue->id);
						}
						return;
					}

					$comment = new \Model\Issue\Comment();
					$comment->user_id = $this->_userId;
					$comment->issue_id = $issue->id;
					$comment->text = $post["text"];
					$comment->created_date = $this->now();
					$comment->save();

					$notification = \Helper\Notification::instance();
					$notification->issue_comment($issue->id, $comment->id);

					if($f3->get("AJAX")) {
						$this->_printJson(
							array(
								"id" => $comment->id,
								"text" => \Helper\View::instance()->parseText($comment->text, array("hashtags" => false)),
								"date_formatted" => date("D, M j, Y \\a\\t g:ia", \Helper\View::instance()->utc2local(time())),
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
					$this->_requireLogin(1);
					$watching = new \Model\Issue\Watcher;
					// Loads just in case the user is already a watcher
					$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $post["user_id"]));
					$watching->issue_id = $issue->id;
					$watching->user_id = $post["user_id"];
					$watching->save();

					if($f3->get("AJAX"))
						return;
					break;

				case "remove_watcher":
					$this->_requireLogin(1);
					$watching = new \Model\Issue\Watcher;
					$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $post["user_id"]));
					$watching->delete();

					if($f3->get("AJAX"))
						return;
					break;

				case "add_dependency":
					$this->_requireLogin(2);
					$dependencies = new \Model\Issue\Dependency;
					// Loads just in case the task  is already a dependency
					$dependencies->load(array("issue_id = ? AND dependency_id = ?", $issue->id, $post["id"]));
					$dependencies->issue_id = $issue->id;
					$dependencies->dependency_id = $post["id"];
					$dependencies->dependency_type = $post["type_id"];
					$dependencies->save();

					if($f3->get("AJAX"))
						return;
					break;

				case "add_dependent":
					$this->_requireLogin(2);
					$dependencies = new \Model\Issue\Dependency;
					// Loads just in case the task  is already a dependency
					$dependencies->load(array("issue_id = ? AND dependency_id = ?",  $post["id"],  $issue->id));
					$dependencies->dependency_id = $issue->id;
					$dependencies->issue_id = $post["id"];
					$dependencies->dependency_type = $post["type_id"];
					$dependencies->save();

					if($f3->get("AJAX"))
						return;
					break;

				case "remove_dependency":
					$this->_requireLogin(2);
					$dependencies = new \Model\Issue\Dependency;
					$dependencies->load($post["id"]);
					$dependencies->delete();

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
		if($issue->owner_id) {
			$owner->load($issue->owner_id);
		}

		$files = new \Model\Issue\File\Detail;
		$f3->set("files", $files->find(array("issue_id = ? AND deleted_date IS NULL", $issue->id)));

		if($issue->sprint_id) {
			$sprint = new \Model\Sprint();
			$sprint->load($issue->sprint_id);
			$f3->set("sprint", $sprint);
		}

		$watching = new \Model\Issue\Watcher;
		$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $this->_userId));
		$f3->set("watching", !!$watching->id);

		$f3->set("issue", $issue);
		$f3->set("ancestors", $issue->getAncestors());
		$f3->set("type", $type);
		$f3->set("author", $author);
		$f3->set("owner", $owner);

		$comments = new \Model\Issue\Comment\Detail;
		$f3->set("comments", $comments->find(array("issue_id = ?", $issue->id), array("order" => "created_date DESC")));

		// Extra data needed for inline edit form
		$status = new \Model\Issue\Status;
		$f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

		$priority = new \Model\Issue\Priority;
		$f3->set("priorities", $priority->find(null, array("order" => "value DESC"), $f3->get("cache_expire.db")));

		$sprint = new \Model\Sprint;
		$f3->set("sprints", $sprint->find(array("end_date >= ? OR id = ?", $this->now(false), $issue->sprint_id), array("order" => "start_date ASC")));

		$users = new \Model\User;
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));
		$f3->set("groups", $users->find("deleted_date IS NULL AND role = 'group'", array("order" => "name ASC")));

		$this->_render("issues/single.html");

	}

	public function single_history($f3, $params) {
		// Build updates array
		$updates_array = array();
		$update_model = new \Model\Custom("issue_update_detail");
		$updates = $update_model->find(array("issue_id = ?", $params["id"]), array("order" => "created_date DESC"));
		foreach($updates as $update) {
			$update_array = $update->cast();
			$update_field_model = new \Model\Issue\Update\Field;
			$update_array["changes"] = $update_field_model->find(array("issue_update_id = ?", $update["id"]));
			$updates_array[] = $update_array;
		}

		$f3->set("updates", $updates_array);

		$this->_printJson(array(
			"total" => count($updates),
			"html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/history.html"))
		));
	}

	public function single_related($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

		if($issue->id) {
			$f3->set("issue", $issue);
			$issues = new \Model\Issue\Detail;
			if($f3->get("issue_type.project") == $issue->type_id || !$issue->parent_id) {
				$searchparams = array("parent_id = ? AND deleted_date IS NULL", $issue->id);
				$orderparams = array("order" => "status_closed, priority DESC, due_date");
				$found_issues = $issues->find($searchparams, $orderparams);
				$f3->set("issues", $found_issues);
				$f3->set("parent", $issue);
			} else {
				if($issue->parent_id) {
					$searchparams = array("(parent_id = ? OR parent_id = ?) AND parent_id IS NOT NULL AND parent_id <> 0 AND deleted_date IS NULL AND id <> ?", $issue->parent_id, $issue->id, $issue->id);
					$orderparams = array('order' => "status_closed, priority DESC, due_date");
					$found_issues = $issues->find($searchparams, $orderparams);

					$f3->set("issues", $found_issues);

					$parent = new \Model\Issue;
					$parent->load($issue->parent_id);
					$f3->set("parent", $parent);
				} else {
					$f3->set("issues", array());
				}
			}

			$searchparams[0] = $searchparams[0]  . " AND status_closed = 0";
			$openissues = $issues->count($searchparams);

			$this->_printJson(array(
				"total" => count($f3->get("issues")),
				"open" => $openissues,
				"html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/related.html"))
			));
		} else {
			$f3->error(404);
		}
	}

	public function single_watchers($f3, $params) {
		$watchers = new \Model\Custom("issue_watcher_user");
		$f3->set("watchers", $watchers->find(array("issue_id = ?", $params["id"])));
		$users = new \Model\User;
		$f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", array("order" => "name ASC")));

		$this->_printJson(array(
			"total" => count($f3->get("watchers")),
			"html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/watchers.html"))
		));
	}

	public function single_dependencies($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

		if($issue->id) {
			$dependencies = new \Model\Issue\Dependency;
			$f3->set("dependencies", $dependencies->findby_issue($issue->id));
			$f3->set("dependents", $dependencies->findby_dependent($issue->id));

			$this->_printJson(array(
				"total" => count($f3->get("dependencies")) + count($f3->get("dependents")),
				"html" => $this->_cleanJson(\Helper\View::instance()->render("issues/single/dependencies.html"))
			));
		} else {
			$f3->error(404);
		}
	}

	public function single_delete($f3, $params) {
		$this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load($params["id"]);
		$user = $f3->get("user_obj");
		if($user->role == "admin" || $user->rank >= 3 || $issue->author_id == $user->id) {
			$issue->delete();
			$f3->reroute("/issues/{$issue->id}");
		} else {
			$f3->error(403);
		}
	}

	public function single_undelete($f3, $params) {
		$this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load($params["id"]);
		$user = $f3->get("user_obj");
		if($user->role == "admin" || $user->rank >= 3 || $issue->author_id == $user->id) {
			$issue->restore();
			$f3->reroute("/issues/{$issue->id}");
		} else {
			$f3->error(403);
		}
	}

	public function comment_delete($f3, $params) {
		$this->_requireLogin(3);
		$comment = new \Model\Issue\Comment;
		$comment->load($f3->get("POST.id"));
		$comment->delete();
		$this->_printJson(array("id" => $f3->get("POST.id")) + $comment->cast());
	}

	public function file_delete($f3, $params) {
		$this->_requireLogin(2);
		$file = new \Model\Issue\File;
		$file->load($f3->get("POST.id"));
		$file->delete();
		$this->_printJson($file->cast());
	}

	public function file_undelete($f3, $params) {
		$this->_requireLogin(2);
		$file = new \Model\Issue\File;
		$file->load($f3->get("POST.id"));
		$file->deleted_date = null;
		$file->save();
		$this->_printJson($file->cast());
	}

	public function search($f3, $params) {
		$query = "%" . $f3->get("GET.q") . "%";
		if(preg_match("/^#([0-9]+)$/", $f3->get("GET.q"), $matches)){
			$f3->reroute("/issues/{$matches[1]}");
		}

		$issues = new \Model\Issue\Detail;

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
		$this->_render("issues/search.html");
	}

	public function upload($f3, $params) {
		$user_id = $this->_requireLogin(2);

		$issue = new \Model\Issue;
		$issue->load(array("id=? AND deleted_date IS NULL", $f3->get("POST.issue_id")));
		if(!$issue->id) {
			$f3->error(404);
			return;
		}

		$web = \Web::instance();

		$f3->set("UPLOADS", "uploads/".date("Y")."/".date("m")."/");
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

		$web->receive(
			function($file) use($f3, $orig_name, $user_id, $issue) {

				if($file['size'] > $f3->get("files.maxsize"))
					return false;

				$newfile = new \Model\Issue\File;
				$newfile->issue_id = $issue->id;
				$newfile->user_id = $user_id;
				$newfile->filename = $orig_name;
				$newfile->disk_filename = $file['name'];
				$newfile->disk_directory = $f3->get("UPLOADS");
				$newfile->filesize = $file['size'];
				$newfile->content_type = $file['type'];
				$newfile->digest = md5_file($file['tmp_name']);
				$newfile->created_date = date("Y-m-d H:i:s");
				$newfile->save();
				$f3->set('file_id', $newfile->id);

				return true; // moves file from php tmp dir to upload dir
			},
			$overwrite,
			$slug
		);

		if($f3->get("POST.text")) {
			$comment = new \Model\Issue\Comment;
			$comment->user_id = $this->_userId;
			$comment->issue_id = $issue->id;
			$comment->text = $f3->get("POST.text");
			$comment->created_date = $this->now();
			$comment->file_id = $f3->get('file_id');
			$comment->save();

			$notification = \Helper\Notification::instance();
			$notification->issue_comment($issue->id, $comment->id);
		} else {
			$notification = \Helper\Notification::instance();
			$notification->issue_file($issue->id, $f3->get("file_id"));
		}

		$f3->reroute("/issues/" . $issue->id);
	}

	/**
	 * Project Overview action
	 * @param  Base $f3
	 * @param  array $params
	 */
	public function project_overview($f3, $params) {

		// Load issue
		$project = new \Model\Issue\Detail;
		$project->load($params["id"]);
		if(!$project->id) {
			$f3->error(404);
			return;
		}
		if($project->type_id != $f3->get("issue_type.project")) {
			$f3->error(400, "Issue is not a project.");
			return;
		}

		/**
		 * Helper function to get a percentage of completed issues across the entire tree
		 * @param   Issue $issue
		 * @var     callable $completeCount This function, required for recursive calls
		 * @return  array
		 */
		$completeCount = function(\Model\Issue &$issue) use(&$completeCount) {
			$total = 0;
			$complete = 0;
			if($issue->id) {
				$total ++;
				if($issue->closed_date) {
					$complete ++;
				}
				foreach($issue->getChildren() as $child) {
					$result = $completeCount($child);
					$total += $result["total"];
					$complete += $result["complete"];
				}
			}
			return array(
				"total" => $total,
				"complete" => $complete
			);
		};
		$f3->set("stats", $completeCount($project));

		/**
		 * Helper function for recursive tree rendering
		 * @param   Issue $issue
		 * @var     callable $renderTree This function, required for recursive calls
		 */
		$renderTree = function(\Model\Issue &$issue, $level = 0) use(&$renderTree) {
			if($issue->id) {
				$f3 = \Base::instance();
				$children = $issue->getChildren();
				$hive = array("issue" => $issue, "children" => $children, "dict" => $f3->get("dict"), "site" => $f3->get("site"), "level" => $level, "issue_type" => $f3->get("issue_type"));
				echo \Helper\View::instance()->render("issues/project/tree-item.html", "text/html", $hive);
				if($children) {
					foreach($children as $item) {
						$renderTree($item, $level + 1);
					}
				}
			}
		};
		$f3->set("renderTree", $renderTree);

		// Render view
		$f3->set("project", $project);
		$f3->set("title", $project->type_name . " #" . $project->id  . ": " . $project->name . " - " . $f3->get("dict.project_overview"));
		$this->_render("issues/project.html");

	}


	/**
	 * decide if the user can view a private issue or project
	 * @return array
	 */
	protected function _checkPrivate() {

	}

}
