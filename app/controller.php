<?php

abstract class Controller {

	/**
	 * Require a user to be logged in. Redirects to /login if a session is not found.
	 * @return int|bool
	 */
	protected function _requireLogin() {
		$f3 = \Base::instance();
		if($id = $f3->get("user.id")) {
			return $id;
		} else {
			if(empty($_GET)) {
				$f3->reroute("/login?to=" . urlencode($f3->get("PATH")));
			} else {
				$f3->reroute("/login?to=" . urlencode($f3->get("PATH")) . urlencode("?" . http_build_query($_GET)));
			}
			$f3->unload();
			return false;
		}
	}

	/**
	 * Require a user to be an administrator. Throws HTTP 403 if logged in, but not an admin.
	 * @return int|bool
	 */
	protected function _requireAdmin() {
		$id = $this->_requireLogin();

		$f3 = \Base::instance();
		if($f3->get("user.role") == "admin") {
			return $id;
		} else {
			$f3->error(403);
			$f3->unload();
			return false;
		}
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
	function now($time = true) {
		return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
	}

}
