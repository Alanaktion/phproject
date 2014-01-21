<?php

namespace Controller;

class Backlog extends Base {

	public function index($f3, $params) {
		echo \Template::instance()->render("backlog/index.html");
	}
	

}
