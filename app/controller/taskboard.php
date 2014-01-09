<?php

namespace Controller;

class Taskboard extends Base {

	public function index($f3, $params) {
		echo \Template::instance()->render("taskboard/index.html");
	}
	

}
