<?php

namespace Controller\Api;

class Issues extends \Controller\Api\Base {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireAuth();
	}

	// Converts an issue into a Redmine API-style multidimensional array
	// This isn't pretty.
	protected function issue_multiarray(\Model\Issue\Detail $issue) {
		$casted = $issue->cast();

		// Convert ALL the fields!
		$result = array();
		$result["tracker"] = array(
			"id" => $issue->type_id,
			"name" => $issue->type_name
		);
		$result["status"] = array(
			"id" => $issue->status,
			"name" => $issue->status_name
		);
		$result["priority"] = array(
			"id" => $issue->priority_id,
			"name" => $issue->priority_name
		);
		$result["author"] = array(
			"id" => $issue->author_id,
			"name" => $issue->author_name,
			"username" => $issue->author_username,
			"email" => $issue->author_email,
			"task_color" => $issue->author_task_color
		);
		$result["owner"] = array(
			"id" => $issue->owner_id,
			"name" => $issue->owner_name,
			"username" => $issue->owner_username,
			"email" => $issue->owner_email,
			"task_color" => $issue->owner_task_color
		);
		if(!empty($issue->sprint_id)) {
			$result["sprint"] = array(
				"id" => $issue->sprint_id,
				"name" => $issue->sprint_name,
				"start_date" => $issue->sprint_start_date,
				"end_date" => $issue->sprint_end_date,
			);
		}

		// Remove redundant fields
		foreach($issue->schema() as $i=>$val) {
			if(
				substr_count($i, "type_") ||
				substr_count($i, "status_") ||
				substr_count($i, "priority_") ||
				substr_count($i, "author_") ||
				substr_count($i, "owner_") ||
				substr_count($i, "sprint_") ||
				$i == "has_due_date"
			) {
				unset($casted[$i]);
			}
		}

		return array_replace($casted, $result);
	}

	// Get a list of issues
	public function get($f3, $params) {
		$issue = new \Model\Issue\Detail();
		$result = $issue->paginate(
			$f3->get("GET.offset") / $f3->get("GET.limit") ?: 30,
			$f3->get("GET.limit") ?: 30
		);

		$issues = array();
		foreach($result["subset"] as $iss) {
			$issues[] = $this->issue_multiarray($iss);
		}

		print_json(array(
			"total_count" => $result["total"],
			"limit" => $result["limit"],
			"issues" => $issues,
			"offset" => $result["pos"] * $result["limit"]
		));
	}

	// Create a new issue
	public function post($f3, $params) {
		if($_POST) {
			// By default, use standard HTTP POST fields
			$post = $_POST;
		} else {

			// For Redmine compatibility, also accept a JSON object
			try {
				$post = json_decode(file_get_contents('php://input'), true);
			} catch (Exception $e) {
				print_json(array(
					"error" => "Unable to parse input"
				));
				return false;
			}

			if(!empty($post["issue"])) {
				$post = $post["issue"];
			}

			// Convert Redmine names to Phproject names
			if(!empty($post["subject"])) {
				$post["name"] = $post["subject"];
			}
			if(!empty($post["parent_issue_id"])) {
				$post["parent_id"] = $post["parent_issue_id"];
			}
			if(!empty($post["tracker_id"])) {
				$post["type_id"] = $post["tracker_id"];
			}
			if(!empty($post["assigned_to_id"])) {
				$post["owner_id"] = $post["assigned_to_id"];
			}
			if(!empty($post["fixed_version_id"])) {
				$post["sprint_id"] = $post["fixed_version_id"];
			}

		}

		// Ensure a status ID is added
		if(!empty($post["status_id"])) {
			$post["status"] = $post["status_id"];
		}
		if(empty($post["status"])) {
			$post["status"] = 1;
		}

		// Verify the required "name" field is passed
		if(empty($post["name"])) {
			$this->_error("The 'name' field is required.");
		}

		// Verify given values are valid (types, statueses, priorities)
		if(!empty($post["type_id"])) {
			$type = new \Model\Issue\Type;
			$type->load($post["type_id"]);
			if(!$type->id) {
				$this->_error("The 'type_id' field is not valid.");
			}
		}
		if(!empty($post["parent_id"])) {
			$parent = new \Model\Issue;
			$parent->load($post["parent_id"]);
			if(!$parent->id) {
				$this->_error("The 'type_id' field is not valid.");
			}
		}
		if(!empty($post["status"])) {
			$status = new \Model\Issue\Status;
			$status->load($post["status"]);
			if(!$status->id) {
				$this->_error("The 'status' field is not valid.");
			}
		}
		if(!empty($post["priority_id"])) {
			$priority = new \Model\Issue\Priority;
			$priority->load(array("value" => $post["priority_id"]));
			if(!$priority->id) {
				$this->_error("The 'priority_id' field is not valid.");
			}
		}

		// Create a new issue based on the data
		$issue = new \Model\Issue();
		$issue->author_id = $this->_userId;
		$issue->name = trim($post["name"]);
		$issue->type_id = empty($post["type_id"]) ? 1 : $post["type_id"];
		$issue->priority_id = empty($post["priority_id"]) ? 0 : $post["priority_id"];
		if(!empty($post["description"])) {
			$issue->description = $post["description"];
		}
		if(!empty($post["parent_id"])) {
			$issue->parent_id = $post["parent_id"];
		}
		if(!empty($post["assigned_to_id"])) {
			$issue->owner_id = $post["owner_id"];
		}

		$issue->save();
		print_json($issue->cast());
	}

	// Get a single issue's details
	public function single_get($f3, $params) {
		$issue = new \Model\Issue\Detail();
		$issue->load($params["id"]);
		if($issue->id) {
			print_json(array("issue" => $this->issue_multiarray($issue)));
		} else {
			$f3->error(404);
		}
	}

	// Update a single issue
	public function single_put($f3, $params) {

	}

	// Delete a single issue
	public function single_delete($f3, $params) {
		$issue = new \Model\Issue;
		$issue->load($params["id"]);
		$issue->delete();

		print_json(array(
			"deleted" => $params["id"]
		));
	}

}
