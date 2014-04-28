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

	public function post($f3, $params) {

	}

	public function single_get($f3, $params) {
		$issue = new \Model\Issue\Detail();
		$issue->load($params["id"]);
		if($issue->id) {
			print_json(array("issue" => $this->issue_multiarray($issue)));
		} else {
			$f3->error(404);
		}
	}

	public function single_put($f3, $params) {

	}

	public function single_delete($f3, $params) {

	}

}
