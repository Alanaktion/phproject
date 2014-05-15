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

function _make_email_clickable_cb($m) {
    $email = $m[2].'@'.$m[3];
    return $m[1]."<a href=\"mailto:$email\">$email</a>";
}

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

function createDateRangeArray($strDateFrom, $strDateTo) {
    // takes two dates formatted as YYYY-MM-DD and creates an
    // inclusive array of the dates between the from and to dates.

    $aryRange=array();

    $iDateFrom=mktime(1,0,0,substr($strDateFrom,5,2),substr($strDateFrom,8,2),substr($strDateFrom,0,4));
    $iDateTo=mktime(1,0,0,substr($strDateTo,5,2),substr($strDateTo,8,2),substr($strDateTo,0,4));

    if ($iDateTo>=$iDateFrom) {
        array_push($aryRange,date('Y-m-d',$iDateFrom)); // first entry
        while ($iDateFrom<$iDateTo) {
            $iDateFrom+=86400; // add 24 hours
            array_push($aryRange,date('Y-m-d',$iDateFrom));
        }
    }
    return $aryRange;
}

/* Passes a string through the Textile parser
 * Also converts issue IDs and usernames to links
 *
 * @param $ttl int|bool
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
    $val = preg_replace("/\b(?<!&)#([0-9]+)\b/", "<a href=\"/issues/$1\">#$1</a>", $val);

    // Find usernames and replace with links
    $val = preg_replace("/\s@([a-z0-9_-]+)\s/i", " <a href=\"/user/$1\">@$1</a> ", $val);

    // Convert URLs to links
    $val = make_clickable($val);

    // Cache the value if $ttl was given
    if($ttl !== false) {
        $cache->set("$hash.tex", $val, $ttl);
    }

    // Return the parsed value
    return $val;
}
