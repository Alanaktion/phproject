<?php

namespace Controller;

class Backlog extends Base {

	public function index($f3, $params) {
        $this->_requireLogin();
		echo \Template::instance()->render("backlog/index.html");
	}


}
