<?php

namespace Controller;

class User extends Base {

	public function index($f3, $params) {
		$f3->reroute("/user/account");
	}

	public function dashboard($f3, $params) {
		$f3->reroute("/");
	}

	public function account($f3, $params) {
		$f3->error(404);
	}

}
