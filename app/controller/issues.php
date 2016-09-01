<?php

namespace Controller;

class Issues extends \Controller {

	protected $_userId;

	/**
	 * Require login on new
	 */
	public function __construct() {
		$this->_userId = $this->_requireLogin();
	}

	/**
	 * Clean a string for encoding in JSON
	 * Collapses whitespace, then trims
	 *
	 * @param  string $string
	 * @return string
	 */
	protected function _cleanJson($string) {
		return trim(preg_replace('/\s+/', ' ', $string));
	}

	/**
	 * Build a WHERE clause for issue listings based on the current filters and sort options
	 *
	 * @return array
	 */
	protected function _buildFilter() {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");
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
				$filter_str .= "`$i` LIKE " . $db->quote("%$val%") . " AND ";
			} elseif($i == "status" && $val == "open") {
				$filter_str .= "status_closed = 0 AND ";
			} elseif($i == "status" && $val == "closed") {
				$filter_str .= "status_closed = 1 AND ";
			} elseif($i == "repeat_cycle" && $val == "repeat") {
				$filter_str .= "repeat_cycle IS NOT NULL AND ";
			} elseif($i == "repeat_cycle" && $val == "none") {
				$filter_str .= "repeat_cycle IS NULL AND ";
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
					$filter_str .= "$i = ". $db->quote($val) ." AND ";
				}
			} else {
				$filter_str .= "`$i` = " . $db->quote($val) . " AND ";
			}
		}
		unset($val);
		$filter_str .= " deleted_date IS NULL ";

		// Build SQL ORDER BY string
		$orderby = !empty($args['orderby']) ? $args['orderby'] : "priority";
		$filter["orderby"] = $orderby;
		$ascdesc = !empty($args['ascdesc']) && strtolower($args['ascdesc']) == 'asc' ? "ASC" : "DESC";
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
			case "due":
				$filter_str .= " ORDER BY due_date {$ascdesc}, priority DESC";
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
	 * GET /issues
	 * Display a sortable, filterable issue list
	 *
	 * @param  \Base  $f3
	 */
	public function index($f3) {
		$issues = new \Model\Issue\Detail;

		// Get filter
		$args = $f3->get("GET");
		list($filter, $filter_str) = $this->_buildFilter();

		// Load type if a type_id was passed
		$type = new \Model\Issue\Type;
		if(!empty($args["type_id"])) {
			$type->load($args["type_id"]);
			if($type->id) {
				$f3->set("title", $f3->get("dict.issues") . " - " . $f3->get("dict.by_type") . ": " . $type->name);
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
		$f3->set("old_sprints", $sprint->find(array("end_date < ?", date("Y-m-d")), array("order" => "start_date ASC")));

		$users = new \Model\User;
		$f3->set("users", $users->getAll());
		$f3->set("deleted_users", $users->getAllDeleted());
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
	 * POST /issues/bulk_update
	 * Update a list of issues
	 *
	 * @param \Base $f3
	 */
	public function bulk_update($f3) {
		$post = $f3->get("POST");

		$issue = new \Model\Issue;
		if(!empty($post["id"]) && is_array($post["id"] )) {
			foreach($post["id"] as $id) {
				// Updating existing issue.
				$issue->load($id);
				if($issue->id) {

					if(!empty($post["update_copy"])) {
						$issue = $issue->duplicate(false);
					}

					// Diff contents and save what's changed.
					foreach($post as $i=>$val) {
						if(
							$issue->exists($i)
							&& $i != "id"
							&& $issue->$i != $val
							&& (!empty($val) || $val === "0")
						) {
							// Allow setting to Not Assigned
							if(($i == "owner_id" || $i == "sprint_id") && $val == -1) {
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
		}

		$f3->reroute("/issues?" . $post["url_query"]);
	}

	/**
	 * GET /issues/export
	 * Export a list of issues
	 *
	 * @param  \Base  $f3
	 */
	public function export($f3) {
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
	 * Get /issues/new/@type
	 * Create a new issue
	 *
	 * @param \Base $f3
	 */
	public function add($f3) {
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
			$parent_id = $f3->get("PARAMS.parent");
			$parent = new \Model\Issue;
			$parent->load(array("id = ?", $parent_id));
			if($parent->id) {
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

	/**
	 * @param \Base $f3
	 */
	public function add_selecttype($f3) {
		$type = new \Model\Issue\Type;
		$f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

		$f3->set("title", $f3->get("dict.new_n", $f3->get("dict.issues")));
		$f3->set("menuitem", "new");
		$this->_render("issues/new.html");
	}

	/**
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function edit($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

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

	/**
	 * GET /issues/close/@id
	 * Close an issue
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function close($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$issue->close();

		$f3->reroute("/issues/" . $issue->id);
	}

	/**
	 * GET /issues/reopen/@id
	 * Reopen an issue
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function reopen($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		if($issue->closed_date) {
			$status = new \Model\Issue\Status;
			$status->load(array("closed = ?", 0));
			$issue->status = $status->id;
			$issue->closed_date = null;
			$issue->save();
		}

		$f3->reroute("/issues/" . $issue->id);
	}

	/**
	 * GET /issues/copy/@id
	 * Copy an issue
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function copy($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

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
	 *
	 * @return \Model\Issue
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
	 * Create a new issue from $_POST
	 *
	 * @return \Model\Issue
	 */
	protected function _saveNew() {
		$f3 = \Base::instance();
		return \Model\Issue::create($f3->get("POST"), !!$f3->get("POST.notify"));
	}

	/**
	 * POST /issues
	 * Save an issue
	 *
	 * @param \Base $f3
	 */
	public function save($f3) {
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
			$f3->reroute("/issues/new/" . $f3->get("POST.type_id"));
		}
	}

	/**
	 * GET /issues/@id
	 * View an issue
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function single($f3, $params) {
		$issue = new \Model\Issue\Detail;
		$issue->load(array("id=?", $params["id"]));
		$user = $f3->get("user_obj");

		if(!$issue->id || ($issue->deleted_date && !($user->role == 'admin' || $user->rank >= \Model\User::RANK_MANAGER || $issue->author_id == $user->id))) {
			$f3->error(404);
			return;
		}

		$type = new \Model\Issue\Type();
		$type->load($issue->type_id);

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

	/**
	 * POST /issues/@id/watchers
	 * Add a watcher
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
	public function add_watcher($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load(array("id=?", $params["id"]));
		if(!$issue->id) {
			$f3->error(404);
		}

		$watcher = new \Model\Issue\Watcher;

		// Loads just in case the user is already a watcher
		$watcher->load(array("issue_id = ? AND user_id = ?", $issue->id, $post["user_id"]));
		if(!$watcher->id) {
			$watcher->issue_id = $issue->id;
			$watcher->user_id = $f3->get("POST.user_id");
			$watcher->save();
		}
	}

	/**
	 * POST /issues/@id/watchers/delete
	 * Delete a watcher
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
	public function delete_watcher($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load(array("id=?", $params["id"]));
		if(!$issue->id) {
			$f3->error(404);
		}

		$watcher = new \Model\Issue\Watcher;

		$watcher->load(array("issue_id = ? AND user_id = ?", $issue->id, $f3->get("POST.user_id")));
		$watcher->delete();
	}

	/**
	 * POST /issues/@id/dependencies
	 * Add a dependency
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
	public function add_dependency($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load(array("id=?", $params["id"]));
		if(!$issue->id) {
			$f3->error(404);
		}

		$dependency = new \Model\Issue\Dependency;

		// Loads just in case the task is already a dependency
		$dependency->load(array("issue_id = ? AND dependency_id = ?", $issue->id, $f3->get("POST.id")));
		$dependency->issue_id = $f3->get("POST.issue_id");
		$dependency->dependency_id = $f3->get("POST.dependency_id");
		$dependency->dependency_type = $f3->get("POST.type");
		$dependency->save();
	}

	/**
	 * POST /issues/@id/dependencies/delete
	 * Delete a dependency
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
	public function delete_dependency($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load(array("id=?", $params["id"]));
		if(!$issue->id) {
			$f3->error(404);
		}

		$dependency = new \Model\Issue\Dependency;
		$dependency->load($f3->get("POST.id"));
		$dependency->delete();
	}

	/**
	 * GET /issues/@id/history
	 * AJAX call for issue history
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
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

	/**
	 * GET /issues/@id/related
	 * AJAX call for related issues
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function single_related($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

		if($issue->id) {
			$f3->set("parent", $issue);

			$issues = new \Model\Issue\Detail;
			if($exclude = $f3->get("GET.exclude")) {
				$searchparams = array("parent_id = ? AND id != ? AND deleted_date IS NULL", $issue->id, $exclude);
			} else {
				$searchparams = array("parent_id = ? AND deleted_date IS NULL", $issue->id);
			}
			$orderparams = array("order" => "status_closed, priority DESC, due_date");
			$f3->set("issues", $issues->find($searchparams, $orderparams));

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

	/**
	 * GET /issues/@id/watchers
	 * AJAX call for issue watchers
	 *
	 * @param \Base $f3
	 * @param array $params
	 */
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

	/**
	 * GET /issues/@id/dependencies
	 * AJAX call for issue dependencies
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function single_dependencies($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);

		if($issue->id) {
			$f3->set("issue", $issue);
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

	/**
	 * POST /issues/delete/@id
	 * Delete an issue
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function single_delete($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);
		$user = $f3->get("user_obj");
		if($user->role == "admin" || $user->rank >= \Model\User::RANK_MANAGER || $issue->author_id == $user->id) {
			$issue->delete();
			$f3->reroute("/issues/{$issue->id}");
		} else {
			$f3->error(403);
		}
	}

	/**
	 * POST /issues/undelete/@id
	 * Un-delete an issue
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function single_undelete($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);
		$user = $f3->get("user_obj");
		if($user->role == "admin" || $user->rank >= \Model\User::RANK_MANAGER || $issue->author_id == $user->id) {
			$issue->restore();
			$f3->reroute("/issues/{$issue->id}");
		} else {
			$f3->error(403);
		}
	}

	/**
	 * POST /issues/comment/save
	 * Save a comment
	 *
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function comment_save($f3) {
		$post = $f3->get("POST");

		$issue = new \Model\Issue;
		$issue->load($post["issue_id"]);

		if(!$issue->id || empty($post["text"])) {
			if($f3->get("AJAX")) {
				$this->_printJson(array("error" => 1));
			} else {
				$f3->reroute("/issues/" . $post["issue_id"]);
			}
			return;
		}

		if($f3->get("POST.action") == "close") {
			$issue->close();
		}

		$comment = \Model\Issue\Comment::create(array(
			"issue_id" => $post["issue_id"],
			"user_id" => $this->_userId,
			"text" => trim($post["text"])
		), !!$f3->get("POST.notify"));

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
		} else {
			$f3->reroute("/issues/" . $comment->issue_id);
		}
	}

	/**
	 * POST /issues/comment/delete
	 * Delete a comment
	 *
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function comment_delete($f3) {
		$this->_requireAdmin();
		$comment = new \Model\Issue\Comment;
		$comment->load($f3->get("POST.id"));
		$comment->delete();
		$this->_printJson(array("id" => $f3->get("POST.id")) + $comment->cast());
	}

	/**
	 * POST /issues/file/delete
	 * Delete a file
	 *
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function file_delete($f3) {
		$file = new \Model\Issue\File;
		$file->load($f3->get("POST.id"));
		$file->delete();
		$this->_printJson($file->cast());
	}

	/**
	 * POST /issues/file/undelete
	 * Un-delete a file
	 *
	 * @param \Base $f3
	 * @throws \Exception
	 */
	public function file_undelete($f3) {
		$file = new \Model\Issue\File;
		$file->load($f3->get("POST.id"));
		$file->deleted_date = null;
		$file->save();
		$this->_printJson($file->cast());
	}

	/**
	 * Build an issue search query WHERE clause
	 *
	 * @param  string $q User query string
	 * @return array  [string, keyword, ...]
	 */
	protected function _buildSearchWhere($q) {
		if(!$q) {
			return array("deleted_date IS NULL");
		}
		$return = array();

		// Build WHERE string
		$keywordParts = array();
		foreach(explode(" ", $q) as $w) {
			$keywordParts[] = "CONCAT(name, description, author_name, owner_name,
				author_username, owner_username) LIKE ?";
			$return[] = "%$w%";
		}
		if(is_numeric($q)) {
			$where = "id = ? OR ";
			array_unshift($return, $q);
		} else {
			$where = "";
		}
		$where .= "(" . implode(" AND ", $keywordParts) . ") AND deleted_date IS NULL";

		// Add WHERE string to return array
		array_unshift($return, $where);
		return $return;
	}

	/**
	 * GET /search
	 * Search for issues
	 *
	 * @param \Base $f3
	 */
	public function search($f3) {
		$q = $f3->get("GET.q");
		if(preg_match("/^#([0-9]+)$/", $q, $matches)){
			$f3->reroute("/issues/{$matches[1]}");
		}

		$issues = new \Model\Issue\Detail;

		$args = $f3->get("GET");
		if(empty($args["page"])) {
			$args["page"] = 0;
		}

		$where = $this->_buildSearchWhere($q);
		if(empty($args["closed"])) {
			$where[0] .= " AND status_closed = '0'";
		}

		$issue_page = $issues->paginate($args["page"], 50, $where, array("order" => "created_date DESC"));
		$f3->set("issues", $issue_page);

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

		$f3->set("show_filters", false);
		$this->_render("issues/search.html");
	}

	/**
	 * POST /issues/upload
	 * Upload a file
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function upload($f3, $params) {
		$user_id = $this->_userId;

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
			if(!!$f3->get("POST.notify")) {
				$notification = \Helper\Notification::instance();
				$notification->issue_comment($issue->id, $comment->id);
			}
		} elseif($newfile->id && !!$f3->get("POST.notify")) {
			$notification = \Helper\Notification::instance();
			$notification->issue_file($issue->id, $f3->get("file_id"));
		}

		$f3->reroute("/issues/" . $issue->id);
	}

	/**
	 * GET /issues/project/@id
	 * Project Overview action
	 *
	 * @param  \Base $f3
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
		 * Helper function to get a percentage of completed issues and some totals across the entire tree
		 * @param   \Model\Issue $issue
		 * @var     callable $completeCount This function, required for recursive calls
		 * @return  array
		 */
		$projectStats = function(\Model\Issue &$issue) use(&$projectStats) {
			$total = 0;
			$complete = 0;
			$hoursSpent = 0;
			$hoursTotal = 0;
			if($issue->id) {
				$total ++;
				if($issue->closed_date) {
					$complete ++;
				}
				if($issue->hours_spent > 0) {
					$hoursSpent += $issue->hours_spent;
				}
				if($issue->hours_total > 0) {
					$hoursTotal += $issue->hours_total;
				}
				foreach($issue->getChildren() as $child) {
					$result = $projectStats($child);
					$total += $result["total"];
					$complete += $result["complete"];
					$hoursSpent += $result["hours_spent"];
					$hoursTotal += $result["hours_total"];
				}
			}
			return array(
				"total" => $total,
				"complete" => $complete,
				"hours_spent" => $hoursSpent,
				"hours_total" => $hoursTotal,
			);
		};
		$f3->set("stats", $projectStats($project));

		/**
		 * Helper function for recursive tree rendering
		 * @param   \Model\Issue $issue
		 * @var     callable $renderTree This function, required for recursive calls
		 */
		$renderTree = function(\Model\Issue &$issue, $level = 0) use(&$renderTree) {
			if($issue->id) {
				$f3 = \Base::instance();
				$children = $issue->getChildren();
				$hive = array("issue" => $issue, "children" => $children, "dict" => $f3->get("dict"), "BASE" => $f3->get("BASE"), "level" => $level, "issue_type" => $f3->get("issue_type"));
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
	 * GET /issues/parent_ajax
	 * Load all matching issues
	 *
	 * @param  \Base $f3
	 */
	public function parent_ajax($f3) {
		if(!$f3->get("AJAX")) {
			$f3->error(400);
		}

		$term = trim($f3->get('GET.q'));
		$results = array();

		$issue = new \Model\Issue;
		if((substr($term, 0, 1) == '#') && is_numeric(substr($term, 1))) {
			$id = (int) substr($term, 1);
			$issues = $issue->find(array('id LIKE ?', $id. '%'), array('limit' => 20));

			foreach($issues as $row) {
				$results[] = array('id'=>$row->get('id'), 'text'=>$row->get('name'));
			}
		}
		elseif(is_numeric($term)) {
			$id = (int) $term;
			$issues = $issue->find(array('(id LIKE ?) OR (name LIKE ?)', $id . '%', '%' . $id . '%'), array('limit' => 20));

			foreach($issues as $row) {
				$results[] = array('id'=>$row->get('id'), 'text'=>$row->get('name'));
			}
		}
		else {
			$issues = $issue->find(array('name LIKE ?', '%' . addslashes($term) . '%'), array('limit' => 20));

			foreach($issues as $row) {
				$results[] = array('id'=>$row->get('id'), 'text'=>$row->get('name'));
			}
		}

		$this->_printJson(array('results' => $results));
	}

}
