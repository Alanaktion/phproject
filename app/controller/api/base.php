<?php

namespace Controller\Api;

abstract class Base extends \Controller\Base {

	/**
	 * Require an API key. Sends an HTTP 403 if one is not supplied.
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
		}

		$user->load(array("api_key", $key));

		if($key && $user->id && $user->api_key) {
			$f3->set("user", $user->cast());
			$f3->set("user_obj", $user);
			return $user->id;
		} else {
			$f3->error(401);
			$f3->unload();
			return false;
		}
	}

	/**
	 * Output an error in a JSON object, stop execution
	 * @param  integer $message
	 */
	protected function _error($message = 1) {
		print_json(array(
			"error" => $message
		));
		exit();
	}

}
