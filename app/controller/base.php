<?php

namespace Controller;

abstract class Base {

	// Require a user to be logged in, will redirect to /login if a session is not found.
	protected function _requireLogin() {
		$f3 = \Base::instance();
		if($id = $f3->get('user.id')) {
			return $id;
		} else {
			$f3->reroute("/login?redirect=" + urlencode("testy"));
			$f3->unload();
			return false;
		}
	}

	protected function _requireAdmin() {
		$id = $this->_requireLogin();

		$f3 = \Base::instance();
		if($f3->get("user.role") == "admin") {
			return true;
		} else {
			$f3->error(403);
			$f3->unload();
			return false;
		}
	}

}
