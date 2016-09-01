<?php

namespace Helper;

class View extends \Template {

	public function __construct() {

		// Register filters
		$this->filter('parseText','$this->parseText');
		$this->filter('formatFilesize','$this->formatFilesize');

		parent::__construct();
	}

	/**
	 * Convert Textile or Markdown to HTML, adding hashtags
	 * @param  string $str
	 * @param  array  $options
	 * @param  int    $ttl
	 * @return string
	 */
	public function parseText($str, $options = array(), $ttl = null) {
		if($options === null) {
			$options = array();
		}
		$options = $options + \Base::instance()->get("parse");

		// Check for cached value if $ttl is set
		if($ttl !== null) {
			$cache = \Cache::instance();
			$hash = sha1($str . json_encode($options));

			// Return value if cached
			if(($str = $cache->get("$hash.tex")) !== false) {
				return $str;
			}
		}

		// Pass to any plugin hooks
		$str = \Helper\Plugin::instance()->callHook("text.parse.before", $str);

		// Run through the parsers based on $options
		if($options["ids"]) {
			$str = $this->_parseIds($str);
		}
		if($options["hashtags"]) {
			$str = $this->_parseHashtags($str);
		}
		if($options["markdown"]) {
			$str = $this->_parseMarkdown($str);
		}
		if($options["textile"]) {
			if($options["markdown"]) {
				// Yes, this is hacky. Please open an issue on GitHub if you
				// know of a better way of supporting Markdown and Textile :)
				$str = html_entity_decode($str);
				$str = preg_replace('/^<p>|<\/p>$/m', "\n", $str);
			}
			$str = $this->_parseTextile($str);
		}
		if($options["emoticons"]) {
			$str = $this->_parseEmoticons($str);
		}
		if($options["urls"]) {
			$str = $this->_parseUrls($str);
		}

		// Simplistic XSS protection
		$str = preg_replace("#</?script>#i", "", $str);

		// Pass to any plugin hooks
		$str = \Helper\Plugin::instance()->callHook("text.parse.after", $str);

		// Cache the value if $ttl is set
		if($ttl !== null) {
			$cache->set("$hash.tex", $str, $ttl);
		}

		return $str;
	}

	/**
 	 * Replaces IDs with links to their corresponding issues
	 * @param  string $str
	 * @return string
	 */
	protected function _parseIds($str) {
		$url = \Base::instance()->get("site.url");

		// Find all IDs
		$count = preg_match_all("/(?<=[^a-z\\/&]#|^#)[0-9]+(?=[^a-z\\/]|$)/i", $str, $matches);
		if(!$count) {
			return $str;
		}

		// Load IDs
		$ids = array();
		foreach($matches[0] as $match) {
			$ids[] = $match;
		}
		$idsStr = implode(",", array_unique($ids));
		$issue = new \Model\Issue;
		$issues = $issue->find(array("id IN ($idsStr)"));

		return preg_replace_callback("/(?<=[^a-z\\/&]|^)#[0-9]+(?=[^a-z\\/]|$)/i", function($matches) use($url, $issues) {
			$id = ltrim($matches[0], "#");
			foreach($issues as $i) {
				if($i->id == $id) {
					$issue = $i;
				}
			}
			if($issue) {
				if($issue->deleted_date) {
					$f3 = \Base::instance();
					if($f3->get("user.role") == "admin" || $f3->get("user.rank") >= \Model\User::RANK_MANAGER || $f3->get("user.id") == $issue->author_id) {
						return "<a href=\"{$url}issues/$id\" style=\"text-decoration: line-through;\">#$id &ndash; " . htmlspecialchars($issue->name) . "</a>";
					} else {
						return "#$id";
					}
				}
				return "<a href=\"{$url}issues/$id\">#$id &ndash; " . htmlspecialchars($issue->name) . "</a>";
			}
			return "<a href=\"{$url}issues/$id\">#$id</a>";
		}, $str);
	}

	/**
 	 * Replaces hashtags with links to their corresponding tag pages
	 * @param  string $str
	 * @return string
	 */
	protected function _parseHashtags($str) {
		return preg_replace_callback("/(?<=[^a-z\\/&]|^)#([a-z][a-z0-9_-]*[a-z0-9]+)(?=[^a-z\\/]|$)/i", function($matches) {
			$url = \Base::instance()->get("site.url");
			$tag = preg_replace("/[_-]+/", "-", $matches[1]);
			return "<a href=\"{$url}tag/$tag\">#$tag</a>";
		}, $str);
	}

	/**
 	 * Replaces URLs with links
	 * @param  string $str
	 * @return string
	 */
	protected function _parseUrls($str) {
		$str = ' ' . $str;

		// In testing, using arrays here was found to be faster
		$str = preg_replace_callback('#([\s>])([\w]+?://[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is', function($matches) {
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
		}, $str);

		$str = preg_replace_callback('#([\s>])((www|ftp)\.[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is', function($m) {
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
		}, $str);

		$str = preg_replace_callback('#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', function($m) {
			$email = $m[2].'@'.$m[3];
			return $m[1]."<a href=\"mailto:$email\">$email</a>";
		}, $str);

		// This one is not in an array because we need it to run last, for cleanup of accidental links within links
		$str = preg_replace("#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i", "$1$3</a>",$str);
		$str = trim($str);

		return $str;
	}

	/**
 	 * Replaces text emoticons with webfont versions
	 * @param  string $str
	 * @return string
	 */
	protected function _parseEmoticons($str) {
		return preg_replace_callback("/([^a-z\\/&]|\\>|^)(3|&gt;)?[:;8B][)(PDOoSs|\/\\\]([^a-z\\/]|\\<|$)/", function($matches) {
			$i = "";
			switch (trim($matches[0], "<> ")) {
				case ":)":
					$i = "smiley";
					break;
				case ";)":
					$i = "wink";
					break;
				case ":(":
					$i = "sad";
					break;
				case "&gt;:(":
					$i = "angry";
					break;
				case "8)":
				case "B)":
					$i = "cool";
					break;
				case "3:)":
				case "&gt;:)":
					$i = "evil";
					break;
				case ":D":
					$i = "happy";
					break;
				case ":P":
					$i = "tongue";
					break;
				case ":o":
				case ":O":
					$i = "shocked";
					break;
				case ":s":
				case ":S":
					$i = "confused";
					break;
				case ":|":
					$i = "neutral";
					break;
				case ":/":
				case ":\\":
					$i = "wondering";
					break;
			}
			if($i) {
				$f3 = \Base::instance();
				$theme = $f3->get("user.theme");
				if(!$theme) {
					$theme = $f3->get("site.theme");
				}
				if(preg_match("/slate|geo|dark|cyborg/i", $theme)) {
					$i .= "2";
				}
				return $matches[1] . "<span class=\"emote emote-{$i}\"></span>" . $matches[count($matches) - 1];
			} else {
				return $matches[0];
			}
		}, $str);
	}

	/**
 	 * Passes a string through the Textile parser
	 * @param  string $str
	 * @return string
	 */
	protected function _parseTextile($str) {
		$tex = new \Textile\Parser('html5');
		$tex->setDimensionlessImages(true);
		return $tex->parse($str);
	}

	/**
 	 * Passes a string through the Markdown parser
	 * @param  string $str
	 * @return string
	 */
	protected function _parseMarkdown($str) {
		$mkd = new \Parsedown();
		$mkd->setUrlsLinked(false);
		return $mkd->text($str);
	}

	/**
	 * Get a human-readable file size
	 * @param  int $filesize
	 * @return string
	 */
	public function formatFilesize($filesize) {
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
	 * Get a Gravatar URL from email address and size, uses global Gravatar configuration
	 * @param  string  $email
	 * @param  integer $size
	 * @return string
	 */
	function gravatar($email, $size = 80) {
		$f3 = \Base::instance();
		$rating = $f3->get("gravatar.rating") ? $f3->get("gravatar.rating") : "pg";
		$default = $f3->get("gravatar.default") ? $f3->get("gravatar.default") : "mm";
		return "//gravatar.com/avatar/" . md5(strtolower($email)) .
				"?s=" . intval($size) .
				"&d=" . urlencode($default) .
				"&r=" . urlencode($rating);
	}

	/**
	 * Convert a UTC timestamp to local time
	 * @param  int $timestamp
	 * @return int
	 */
	function utc2local($timestamp = null) {
		if($timestamp && !is_numeric($timestamp)) {
			$timestamp = @strtotime($timestamp);
		}
		if(!$timestamp) {
			$timestamp = time();
		}

		$f3 = \Base::instance();

		if($f3->exists("site.timeoffset")) {
			$offset = $f3->get("site.timeoffset");
		} else {
			$tz = $f3->get("site.timezone");
			$dtzLocal = new \DateTimeZone($tz);
			$dtLocal = new \DateTime("now", $dtzLocal);
			$offset = $dtzLocal->getOffset($dtLocal);
			$f3->set("site.timeoffset", $offset);
		}

		return $timestamp + $offset;
	}

	/**
	 * Get the current primary language
	 * @return string
	 */
	function lang() {
		$f3 = \Base::instance();
		$langs = $f3->split($f3->get("LANGUAGE"));
		return isset($langs[0]) ? $langs[0] : $f3->get("FALLBACK");
	}

}
