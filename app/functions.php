<?php // Global core functions

// Get a Gravatar URL from email address and size, uses global Gravatar configuration
function gravatar($email, $size = 80) {
	$f3 = Base::instance();
	$rating = $f3->get('gravatar.rating') ? $f3->get('gravatar.rating') : "pg";
	$default = $f3->get('gravatar.default') ? $f3->get('gravatar.default') : "mm";
	return "//gravatar.com/avatar/" . md5(strtolower($email)) .
			"?s=" . intval($size) .
			"&d=" . urlencode($default) .
			"&r=" . urlencode($rating);
}

// HTML escape shortcode
function h($str) {
	return htmlspecialchars($str);
}

// Get current time and date in a MySQL NOW() format
function now($time = true) {
	return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
}

// Output object as JSON and set appropriate headers
function print_json($object) {
	if(!headers_sent()) {
		header("Content-type: application/json");
	}
	echo json_encode($object);
}

// Convert string to simple "slug"
function slugify($s,$lower=true) {
	$spec = 'àáâãäåèéêëìíîïðòóôõöùúûüñç/_,:;';
	$simp = 'aaaaaaeeeeiiiioooooouuuunc-----';
	$s = str_replace(str_split($spec),str_split($simp),trim($lower ? strtolower($s) : $s));
	$s = preg_replace('/[^\w -]/','',$s); // Remove special characters
	$s = str_replace(array('_',' '),'-',$s); // Whitespace to hyphen
	$s = preg_replace('/-+/','-',$s); // Collapse hypens
	return trim($s,'-');
}

class TemplateAddons {
	public static function placehold($args) {
		$attr = $args['@attrib'];
		return sprintf('<img src="data:image/gif;base64,R0lGODlhAQABAID/ AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" style="background-color: #ccc;" width="%u" height="%u">', $attr["width"], $attr["height"]);
	}
}

\Template::instance()->extend("placehold", "TemplateAddons::placehold");
