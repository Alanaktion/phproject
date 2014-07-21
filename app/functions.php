<?php
/**
 * Global core functions
 */

/**
 * Get a Gravatar URL from email address and size, uses global Gravatar configuration
 * @param  string  $email
 * @param  integer $size
 * @return string
 */
function gravatar($email, $size = 80) {
	$f3 = Base::instance();
	$rating = $f3->get("gravatar.rating") ? $f3->get("gravatar.rating") : "pg";
	$default = $f3->get("gravatar.default") ? $f3->get("gravatar.default") : "mm";
	return "//gravatar.com/avatar/" . md5(strtolower($email)) .
			"?s=" . intval($size) .
			"&d=" . urlencode($default) .
			"&r=" . urlencode($rating);
}

/**
 * HTML escape shortcode
 * @param  string $str
 * @return string
 */
function h($str) {
	return htmlspecialchars($str);
}

/**
 * Get current time and date in a MySQL NOW() format
 * @param  boolean $time  Determines whether to include the time in the string
 * @return string
 */
function now($time = true) {
	return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
}

/**
 * Output object as JSON and set appropriate headers
 * @param mixed $object
 */
function print_json($object) {
	if(!headers_sent()) {
		header("Content-type: application/json");
	}
	echo json_encode($object);
}

/**
 * Internal function used by make_clickable
 * @param  array  $matches
 * @return string
 */
function _make_url_clickable_cb($matches) {
	$ret = '';
	$url = $matches[2];

	if(empty($url))
		return $matches[0];
	// removed trailing [.,;:] from URL
	if(in_array(substr($url,-1),array('.',',',';',':')) === true) {
		$ret = substr($url,-1);
		$url = substr($url,0,strlen($url)-1);
	}
	return $matches[1] . "<a href=\"$url\" rel=\"nofollow\" target=\"_blank\">$url</a>".$ret;
}

/**
 * Internal function used by make_clickable
 * @param  array $m
 * @return string
 */
function _make_web_ftp_clickable_cb($m) {
	$s = '';
	$d = $m[2];

	if (empty($d))
		return $m[0];

	// removed trailing [,;:] from URL
	if(in_array(substr($d,-1),array('.',',',';',':')) === true) {
		$s = substr($d,-1);
		$d = substr($d,0,strlen($d)-1);
	}
	return $m[1] . "<a href=\"http://$d\" rel=\"nofollow\" target=\"_blank\">$d</a>".$s;
}

/**
 * Internal function used by make_clickable
 * @param  array $m
 * @return string
 */
function _make_email_clickable_cb($m) {
	$email = $m[2].'@'.$m[3];
	return $m[1]."<a href=\"mailto:$email\">$email</a>";
}

/**
 * Converts recognized URLs and email addresses into HTML hyperlinks
 * @param  string $s
 * @return string
 */
function make_clickable($s) {
	$s = ' '.$s;
	// in testing, using arrays here was found to be faster
	$s = preg_replace_callback('#([\s>])([\w]+?://[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is','_make_url_clickable_cb',$s);
	$s = preg_replace_callback('#([\s>])((www|ftp)\.[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is','_make_web_ftp_clickable_cb',$s);
	$s = preg_replace_callback('#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i','_make_email_clickable_cb',$s);

	// this one is not in an array because we need it to run last, for cleanup of accidental links within links
	$s = preg_replace("#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i", "$1$3</a>",$s);
	$s = trim($s);
	return $s;
}

/**
 * Send an email with the UTF-8 character set
 * @param  string $to
 * @param  string $subject
 * @param  string $body
 * @return bool
 */
function utf8mail($to, $subject, $body) {
	$f3 = \Base::instance();

	// Set content-type with UTF charset
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";

	// Add sender and recipient information
	$headers .= 'To: '. $to . "\r\n";
	$headers .= 'From: '. $f3->get("mail.from") . "\r\n";

	return mail($to, $subject, $body, $headers);
}

/**
 * Takes two dates formatted as YYYY-MM-DD and creates an
 * inclusive array of the dates between the from and to dates.
 * @param  string $strDateFrom
 * @param  string $strDateTo
 * @return array
 */
function createDateRangeArray($strDateFrom, $strDateTo) {
	$aryRange = array();

	$iDateFrom = mktime(1,0,0,substr($strDateFrom,5,2),substr($strDateFrom,8,2),substr($strDateFrom,0,4));
	$iDateTo = mktime(1,0,0,substr($strDateTo,5,2),substr($strDateTo,8,2),substr($strDateTo,0,4));

	if ($iDateTo >= $iDateFrom) {
		$aryRange[] = date('Y-m-d', $iDateFrom); // first entry
		while ($iDateFrom < $iDateTo) {
			$iDateFrom += 86400; // add 24 hours
			$aryRange[] = date('Y-m-d', $iDateFrom);
		}
	}

	return $aryRange;
}

/**
 * Passes a string through the Textile parser,
 * also converts issue IDs and usernames to links
 * @param  string   $str
 * @param  int|bool $ttl
 * @return string
 */
function parseTextile($str, $ttl=false) {
	if($ttl !== false) {
		$cache = \Cache::instance();
		$hash = sha1($str);

		// Return value if cached
		if(($val = $cache->get("$hash.tex")) !== false) {
			return $val;
		}
	}

	// Value wasn't cached, run the parser
	$tex = new \Helper\Textile\Parser();
	$val = $tex->parse($str);

	// Find issue IDs and convert to links
	$val = preg_replace("/(?<=[\s,\(])#([0-9]+)(?=[\s,\)\.,])/", "<a href=\"/issues/$1\">#$1</a>", $val);

	// Find usernames and replace with links
	$val = preg_replace("/(?<=\s)@([a-z0-9_-]+)(?=\s)/i", " <a href=\"/user/$1\">@$1</a> ", $val);

	// Convert URLs to links
	$val = make_clickable($val);

	// Cache the value if $ttl was given
	if($ttl !== false) {
		$cache->set("$hash.tex", $val, $ttl);
	}

	// Return the parsed value
	return $val;
}

/**
 * Get a human-readable file size
 * @param  int $filesize
 * @return string
 */
function format_filesize($filesize) {
	if($filesize > 1073741824) {
		return round($filesize / 1073741824, 2) . " GB";
	} elseif($filesize > 1048576) {
		return round($filesize / 1048576, 2) . " MB";
	} elseif($filesize > 1024) {
		return round($filesize / 1024, 2) . " KB";
	} else {
		return $filesize . " bytes";
	}
}

/**
 * Convert a UTC timestamp to local time
 * @param  int $timestamp
 * @return int
 */
function utc2local($timestamp) {
	$f3 = Base::instance();
	if($f3->exists("site.timeoffset")) {
		$offset = $f3->get("site.timeoffset");
	} else {
		$tz = $f3->get("site.timezone");
		$dtzLocal = new DateTimeZone($tz);
		$dtLocal = new DateTime("now", $dtzLocal);
		$offset = $dtzLocal->getOffset($dtLocal);
	}
	return $timestamp + $offset;
}
