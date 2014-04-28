<?php

namespace Controller\Api;

class Issues extends \Controller\Api\Base {

	protected $_userId;

	public function __construct() {
		$this->_userId = $this->_requireAuth();
	}

	public function get($f3, $params) {
		$issue = new \Model\Issue\Detail();
		$issues = $issue->paginate(
			$f3->get("GET.offset"),
			$f3->get("GET.limit") ?: 30
		);

		foreach($issues["subset"] as &$iss) {
			$iss = $iss->cast();
		}

		print_json($issues);
	}

	public function post($f3, $params) {

	}

	public function single_get($f3, $params) {
		$issue = new \Model\Issue\Detail();
		$issue->load($params["id"]);

		print_json($issue->cast());
	}

	public function single_put($f3, $params) {

	}

	public function single_delete($f3, $params) {

	}

}
