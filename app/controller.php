<?php

abstract class Controller {

	/**
	 * Require a user to be logged in.
	 *
	 * Sends HTTP 403 if logged in, but not sufficient rank
	 * Redirects to /login if a session is not found and $rank is > 0
	 *
	 * @param  int $rank
	 * @return int|bool
	 */
	protected function _requireLogin($rank = 1) {
		$f3 = \Base::instance();
		if($id = $f3->get("user.id")) {
			if($f3->get("user.rank") >= $rank) {
				return $id;
			} else {
				$f3->error(403);
				$f3->unload();
				return false;
			}
		} else {
			if($f3->get("site.demo") && is_numeric($f3->get("site.demo"))) {
				$user = new \Model\User();
				$user->load($f3->get("site.demo"));
				if($user->id) {
					$session = new \Model\Session($user->id);
					$session->setCurrent();
					return $user->id;
				} else {
					$f3->set("error", "Auto-login failed, demo user was not found.");
				}
			}
			if($rank > 0 || !$f3->get("site.public_access")) {
				$f3->reroute("/login?to=" . urlencode($f3->get("PATH")) . (empty($_GET) ? '' : urlencode("?" . http_build_query($_GET))));
				$f3->unload();
			}
			return false;
		}
	}

	/**
	 * Require a user to be an administrator.
	 * @param  int $rank
	 * @return int|bool
	 */
	protected function _requireAdmin($rank = 4) {
		return $this->_requireLogin($rank);
	}

	/**
	 * Render a view
	 * @param string  $file
	 * @param string  $mime
	 * @param array   $hive
	 * @param integer $ttl
	 */
	protected function _render($file, $mime = "text/html", array $hive = null, $ttl = 0) {
		echo \Helper\View::instance()->render($file, $mime, $hive, $ttl);
	}

	/**
	 * Output object as JSON and set appropriate headers
	 * @param mixed $object
	 */
	protected function _printJson($object) {
		if(!headers_sent()) {
			header("Content-type: application/json");
		}
		echo json_encode($object);
	}

	/**
	 * Get current time and date in a MySQL NOW() format
	 * @param  boolean $time  Whether to include the time in the string
	 * @return string
	 */
	final public function now($time = true) {
		return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
	}

}
