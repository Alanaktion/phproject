<?php // Global core functions

// Get a Gravatar URL from email address and size, uses global Gravatar configuration
function gravatar($email, $size = 80) {
	$f3 = Base::instance();
	$rating = $f3->get("gravatar.rating") ? $f3->get("gravatar.rating") : "pg";
	$default = $f3->get("gravatar.default") ? $f3->get("gravatar.default") : "mm";
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
	$spec = "àáâãäåèéêëìíîïðòóôõöùúûüñç/_,:;";
	$simp = "aaaaaaeeeeiiiioooooouuuunc-----";
	$s = str_replace(str_split($spec),str_split($simp),trim($lower ? strtolower($s) : $s));
	$s = preg_replace("/[^\w -]/","",$s); // Remove special characters
	$s = str_replace(array("_"," "),"-",$s); // Whitespace to hyphen
	$s = preg_replace("/-+/","-",$s); // Collapse hypens
	return trim($s,"-");
}

// http_response_code() for PHP < 5.4.0
if (!function_exists("http_response_code")) {
	function http_response_code($code = NULL) {
		if ($code !== NULL) {
			switch ($code) {
				case 100: $text = "Continue"; break;
				case 101: $text = "Switching Protocols"; break;
				case 200: $text = "OK"; break;
				case 201: $text = "Created"; break;
				case 202: $text = "Accepted"; break;
				case 203: $text = "Non-Authoritative Information"; break;
				case 204: $text = "No Content"; break;
				case 205: $text = "Reset Content"; break;
				case 206: $text = "Partial Content"; break;
				case 300: $text = "Multiple Choices"; break;
				case 301: $text = "Moved Permanently"; break;
				case 302: $text = "Moved Temporarily"; break;
				case 303: $text = "See Other"; break;
				case 304: $text = "Not Modified"; break;
				case 305: $text = "Use Proxy"; break;
				case 400: $text = "Bad Request"; break;
				case 401: $text = "Unauthorized"; break;
				case 402: $text = "Payment Required"; break;
				case 403: $text = "Forbidden"; break;
				case 404: $text = "Not Found"; break;
				case 405: $text = "Method Not Allowed"; break;
				case 406: $text = "Not Acceptable"; break;
				case 407: $text = "Proxy Authentication Required"; break;
				case 408: $text = "Request Time-out"; break;
				case 409: $text = "Conflict"; break;
				case 410: $text = "Gone"; break;
				case 411: $text = "Length Required"; break;
				case 412: $text = "Precondition Failed"; break;
				case 413: $text = "Request Entity Too Large"; break;
				case 414: $text = "Request-URI Too Large"; break;
				case 415: $text = "Unsupported Media Type"; break;
				case 500: $text = "Internal Server Error"; break;
				case 501: $text = "Not Implemented"; break;
				case 502: $text = "Bad Gateway"; break;
				case 503: $text = "Service Unavailable"; break;
				case 504: $text = "Gateway Time-out"; break;
				case 505: $text = "HTTP Version not supported"; break;
				default:
					die("Unknown http status code: " . htmlentities($code));
				break;
			}
			$protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0";
			header($protocol . " " . $code . " " . $text);
			$GLOBALS["http_response_code"] = $code;
		} else {
			$code = isset($GLOBALS["http_response_code"]) ? $GLOBALS["http_response_code"] : 200;
		}
		return $code;
	}
}

class TemplateAddons {
	public static function placehold($args) {
		$attr = $args["@attrib"];
		return sprintf('<img src="data:image/gif;base64,R0lGODlhAQABAID/ AMDAwAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==" style="background-color: #ccc;" width="%u" height="%u">', $attr["width"], $attr["height"]);
	}
}

\Template::instance()->extend("placehold", "TemplateAddons::placehold");
