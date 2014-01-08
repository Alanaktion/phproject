<?php
// Global core functions

function gravatar($email, $size = 80) {
	$f3 = Base::instance();
	$rating = $f3->get('gravatar.rating') ? $f3->get('gravatar.rating') : "pg";
	$default = $f3->get('gravatar.default') ? $f3->get('gravatar.default') : "mm";
	return "//gravatar.com/avatar/" . md5(strtolower($email)) .
			"?s=" . intval($size) .
			"&d=" . urlencode($default) .
			"&r=" . urlencode($rating);
}

function h($str) {
	return htmlspecialchars($str);
}

function now($time = true) {
	return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
}
