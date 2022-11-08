<?php

namespace Helper;

class View extends \Template
{
    public function __construct()
    {
        // Register filters
        $this->filter('parseText', '$this->parseText');
        $this->filter('formatFilesize', '$this->formatFilesize');

        parent::__construct();
    }

    /**
     * Convert Textile or Markdown to HTML, adding hashtags
     * @param  string $str
     * @param  array  $options
     * @param  int    $ttl
     * @return string
     */
    public function parseText($str, $options = [], $ttl = null)
    {
        if ($str === null || $str === '') {
            return '';
        }
        if ($options === null) {
            $options = [];
        }
        $options = $options + \Base::instance()->get("parse");

        // Check for cached value if $ttl is set
        $cache = \Cache::instance();
        $hash = null;
        if ($ttl !== null) {
            $hash = sha1($str . json_encode($options, JSON_THROW_ON_ERROR));

            // Return value if cached
            if (($str = $cache->get("$hash.tex")) !== false) {
                return $str;
            }
        }

        // Pass to any plugin hooks
        $str = \Helper\Plugin::instance()->callHook("text.parse.before", $str);

        // Run through the parsers based on $options
        if ($options["ids"]) {
            $str = $this->_parseIds($str);
        }
        if ($options["hashtags"]) {
            $str = $this->_parseHashtags($str);
        }
        if ($options["emoticons"]) {
            $str = $this->_parseEmoticons($str);
        }
        if ($options["markdown"]) {
            $str = $this->_parseMarkdown($str);
        }
        if ($options["textile"]) {
            $escape = true;
            if ($options["markdown"]) {
                // Yes, this is hacky. Please open an issue on GitHub if you
                // know of a better way of supporting Markdown and Textile :)
                $str = html_entity_decode($str);
                $str = preg_replace('/^<p>|<\/p>$/m', "\n", $str);
                $escape = false;
            }
            $str = $this->_parseTextile($str, $escape);
        }
        if (!$options["markdown"] && !$options['textile']) {
            $str = nl2br(\Base::instance()->encode($str), false);
        }
        if ($options["urls"]) {
            $str = $this->_parseUrls($str);
        }

        // Simplistic XSS protection
        $antiXss = new \voku\helper\AntiXSS();
        $str = $antiXss->xss_clean($str);

        // Pass to any plugin hooks
        $str = \Helper\Plugin::instance()->callHook("text.parse.after", $str);

        // Cache the value if $ttl is set
        if ($ttl !== null) {
            $cache->set("$hash.tex", $str, $ttl);
        }

        return $str;
    }

    /**
     * Replaces IDs with links to their corresponding issues
     * @param  string $str
     * @return string
     */
    protected function _parseIds($str)
    {
        $url = \Base::instance()->get("site.url");

        // Find all IDs
        $count = preg_match_all("/(?<=[^a-z\\/&]#|^#)[0-9]+(?=[^a-z\\/]|$)/i", $str, $matches);
        if (!$count) {
            return $str;
        }

        // Load IDs
        $ids = [];
        foreach ($matches[0] as $match) {
            $ids[] = $match;
        }
        $idsStr = implode(",", array_unique($ids));
        $issue = new \Model\Issue();
        $issues = $issue->find(["id IN ($idsStr)"]);

        return preg_replace_callback("/(?<=[^a-z\\/&]|^)#[0-9]+(?=[^a-z\\/]|$)/i", function ($matches) use ($url, $issues) {
            $issue = null;
            $id = ltrim($matches[0], "#");
            foreach ($issues as $i) {
                if ($i->id == $id) {
                    $issue = $i;
                }
            }
            if ($issue) {
                if ($issue->deleted_date) {
                    $f3 = \Base::instance();
                    if ($f3->get("user.role") == "admin" || $f3->get("user.rank") >= \Model\User::RANK_MANAGER || $f3->get("user.id") == $issue->author_id) {
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
    protected function _parseHashtags($str)
    {
        return preg_replace_callback("/(?<=[^a-z\\/&]|^)#([a-z][a-z0-9_-]*[a-z0-9]+)(?=[^a-z\\/]|$)/i", function ($matches) {
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
    protected function _parseUrls($str)
    {
        $str = ' ' . $str;

        // In testing, using arrays here was found to be faster
        $str = preg_replace_callback('#([\s>])([\w]+?://[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is', function ($matches) {
            $ret = '';
            $url = $matches[2];

            if (empty($url)) {
                return $matches[0];
            }
            // removed trailing [.,;:] from URL
            if (in_array(substr($url, -1), ['.', ',', ';', ':']) === true) {
                $ret = substr($url, -1);
                $url = substr($url, 0, strlen($url) - 1);
            }
            return $matches[1] . "<a href=\"$url\" rel=\"nofollow\" target=\"_blank\">$url</a>" . $ret;
        }, $str);

        $str = preg_replace_callback('#([\s>])((www|ftp)\.[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is', function ($m) {
            $s = '';
            $d = $m[2];

            if (empty($d)) {
                return $m[0];
            }

            // removed trailing [,;:] from URL
            if (in_array(substr($d, -1), ['.', ',', ';', ':']) === true) {
                $s = substr($d, -1);
                $d = substr($d, 0, strlen($d) - 1);
            }
            return $m[1] . "<a href=\"http://$d\" rel=\"nofollow\" target=\"_blank\">$d</a>" . $s;
        }, $str);

        $str = preg_replace_callback('#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', function ($m) {
            $email = $m[2] . '@' . $m[3];
            return $m[1] . "<a href=\"mailto:$email\">$email</a>";
        }, $str);

        // This one is not in an array because we need it to run last, for cleanup of accidental links within links
        $str = preg_replace("#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i", "$1$3</a>", $str);
        $str = trim($str);

        return $str;
    }

    /**
     * Replaces text emoticons with Emoji versions
     * @param  string $str
     * @return string
     */
    protected function _parseEmoticons($str)
    {
        // Custom Emoji map, based on UTF::emojify
        $map = [
            ':(' => "\xF0\x9F\x99\x81", // frown
            ':)' => "\xF0\x9F\x99\x82", // smile
            '<3' => "\xE2\x9D\xA4\xEF\xB8\x8F", // heart
            ':D' => "\xF0\x9F\x98\x83", // grin
            'XD' => "\xF0\x9F\x98\x86", // laugh
            ';)' => "\xF0\x9F\x98\x89", // wink
            ':P' => "\xF0\x9F\x98\x8B", // tongue
            ':,' => "\xF0\x9F\x98\x8F", // think
            ':/' => "\xF0\x9F\x98\xA3", // skeptic
            '8O' => "\xF0\x9F\x98\xB2", // oops
        ];

        $match = implode('|', array_map(fn ($str) => preg_quote($str, '/'), array_keys($map)));
        $regex = "/([^a-z\\/&]|^)($match)([^a-z\\/]|$)/m";

        return preg_replace_callback($regex, fn ($match) => $match[1] . $map[$match[2]] . $match[3], $str);
    }

    /**
     * Passes a string through the Textile parser
     * @param  string $str
     * @return string
     */
    protected function _parseTextile($str, $escape = true)
    {
        error_reporting(E_ALL ^ E_DEPRECATED); // Temporarily ignore deprecations because this lib is incompatible
        $tex = new \Netcarver\Textile\Parser('html5');
        $tex->setDimensionlessImages(true);
        $tex->setRestricted($escape);
        return $tex->parse($str);
    }

    /**
     * Passes a string through the Markdown parser
     * @param  string $str
     * @return string
     */
    protected function _parseMarkdown($str, $escape = true)
    {
        $mkd = new \Parsedown();
        $mkd->setUrlsLinked(false);
        $mkd->setMarkupEscaped($escape);
        return $mkd->text($str);
    }

    /**
     * Get a human-readable file size
     * @param  int $filesize
     * @return string
     */
    public function formatFilesize($filesize)
    {
        if ($filesize > 1_073_741_824) {
            return round($filesize / 1_073_741_824, 2) . " GB";
        } elseif ($filesize > 1_048_576) {
            return round($filesize / 1_048_576, 2) . " MB";
        } elseif ($filesize > 1024) {
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
    public function gravatar($email, $size = 80)
    {
        $f3 = \Base::instance();
        $rating = $f3->get("gravatar.rating") ?: "pg";
        $default = $f3->get("gravatar.default") ?: "mm";
        return "https://gravatar.com/avatar/" . md5(strtolower($email ?? '')) .
                "?s=" . intval($size) .
                "&d=" . urlencode($default) .
                "&r=" . urlencode($rating);
    }

    /**
     * Get UTC time offset in seconds
     *
     * @return int
     */
    public function timeoffset()
    {
        $f3 = \Base::instance();

        if ($f3->exists("site.timeoffset")) {
            return $f3->get("site.timeoffset");
        } else {
            $tz = $f3->get("site.timezone");
            $dtzLocal = new \DateTimeZone($tz);
            $dtLocal = new \DateTime("now", $dtzLocal);
            $offset = $dtzLocal->getOffset($dtLocal);
            $f3->set("site.timeoffset", $offset);
        }

        return $offset;
    }

    /**
     * Convert a UTC timestamp to local time
     * @param  int|string $timestamp
     * @return int
     */
    public function utc2local($timestamp = null)
    {
        if ($timestamp && !is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        if (!$timestamp) {
            $timestamp = time();
        }

        $offset = $this->timeoffset();

        return $timestamp + $offset;
    }

    /**
     * Get the current primary language
     * @return string
     */
    public function lang()
    {
        $f3 = \Base::instance();
        $langs = $f3->split($f3->get("LANGUAGE"));
        return $langs[0] ?? $f3->get("FALLBACK");
    }
}
