<?php

namespace Controller;

abstract class Api extends \Controller {

	protected $_userId;

	function __construct() {
		\Base::instance()->set("ONERROR", function($f3) {
			if(!headers_sent()) {
				header("Content-type: application/json");
			}
			echo json_encode(array(
				"status" => $f3->get("ERROR.code"),
				"error" => $f3->get("ERROR.text")
			));
		});

		$this->_userId = $this->_requireAuth();
	}

	/**
	 * Require an API key. Sends an HTTP 401 if one is not supplied.
	 * @return int|bool
	 */
	protected function _requireAuth() {
		$f3 = \Base::instance();

		$user = new \Model\User();

		// Use the logged in user if there is one
		if($f3->get("user.api_key")) {
			$key = $f3->get("user.api_key");
		} else {
			$key = false;
		}

		// Check all supported key methods
		if(!empty($_GET["key"])) {
			$key = $_GET["key"];
		} elseif($f3->get("HEADERS.X-Redmine-API-Key")) {
			$key = $f3->get("HEADERS.X-Redmine-API-Key");
		} elseif($f3->get("HEADERS.X-API-Key")) {
			$key = $f3->get("HEADERS.X-API-Key");
		} elseif($f3->get("HEADERS.X-Api-Key")) {
			$key = $f3->get("HEADERS.X-Api-Key");
		}

		$user->load(array("api_key", $key));

		if($key && $user->id && $user->api_key) {
			$f3->set("user", $user->cast());
			$f3->set("user_obj", $user);
			return $user->id;
		} else {
			$f3->error(401);
			return false;
		}
	}

}
