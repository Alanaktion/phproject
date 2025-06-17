<?php

namespace Helper;

use League\CommonMark\GithubFlavoredMarkdownConverter;

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
     */
    public function parseText(string $str, array $options = [], ?int $ttl = null): string
    {
        if ($str === null || $str === '') {
            return '';
        }
        if ($options === null) {
            $options = [];
        }
        $options += \Base::instance()->get("parse");

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
                $str = html_entity_decode((string) $str);
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
        if ($options["ids"]) {
            $str = $this->_parseIds($str);
        }
        if ($options["hashtags"]) {
            $str = $this->_parseHashtags($str);
        }

        // Simplistic XSS protection
        $str = Security::instance()->cleanXss($str);

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
     */
    protected function _parseIds(string $str): string
    {
        $url = \Base::instance()->get("site.url");

        // Find all IDs
        $count = preg_match_all("/(?<=[^a-z\\/&]#|^#)[0-9]+(?=[^a-z\\/]|$)/i", $str, $matches);
        if (!$count) {
            return $str;
        }
        $ids = $matches[0];
        $idsStr = implode(",", array_unique($ids));
        $issue = new \Model\Issue();
        $issues = $issue->find(["id IN ($idsStr)"]);

        return preg_replace_callback("/(?<=[^a-z\\/&]|^)#[0-9]+(?=[^a-z\\/]|$)/i", function (array $matches) use ($url, $issues): string {
            $issue = null;
            $id = ltrim((string) $matches[0], "#");
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
     */
    protected function _parseHashtags(string $str): string
    {
        return preg_replace_callback("/(?<=[^a-z\\/&]|^)#([a-z][a-z0-9_-]*[a-z0-9]+)(?=[^a-z\\/]|$)/i", function (array $matches): string {
            $url = \Base::instance()->get("site.url");
            $tag = preg_replace("/[_-]+/", "-", (string) $matches[1]);
            return "<a href=\"{$url}tag/$tag\">#$tag</a>";
        }, $str);
    }

    /**
     * Replaces URLs with links
     */
    protected function _parseUrls(string $str): string
    {
        $str = ' ' . $str;

        // In testing, using arrays here was found to be faster
        $str = preg_replace_callback('#([\s>])([\w]+?://[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is', function (array $matches): string {
            $ret = '';
            $url = $matches[2];

            if (empty($url)) {
                return $matches[0];
            }
            // removed trailing [.,;:] from URL
            if (in_array(substr($url, -1), ['.', ',', ';', ':'])) {
                $ret = substr($url, -1);
                $url = substr($url, 0, strlen($url) - 1);
            }
            return $matches[1] . "<a href=\"$url\" rel=\"nofollow\" target=\"_blank\">$url</a>" . $ret;
        }, $str);

        $str = preg_replace_callback('#([\s>])((www|ftp)\.[\w\\x80-\\xff\#!$%&~/.\-;:=,?@\[\]+]*)#is', function (array $m): string {
            $s = '';
            $d = $m[2];

            if (empty($d)) {
                return $m[0];
            }

            // removed trailing [,;:] from URL
            if (in_array(substr($d, -1), ['.', ',', ';', ':'])) {
                $s = substr($d, -1);
                $d = substr($d, 0, strlen($d) - 1);
            }
            return $m[1] . "<a href=\"http://$d\" rel=\"nofollow\" target=\"_blank\">$d</a>" . $s;
        }, (string) $str);

        $str = preg_replace_callback('#([\s>])([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})#i', function (array $m): string {
            $email = $m[2] . '@' . $m[3];
            return $m[1] . "<a href=\"mailto:$email\">$email</a>";
        }, (string) $str);

        // This one is not in an array because we need it to run last, for cleanup of accidental links within links
        $str = preg_replace("#(<a( [^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i", "$1$3</a>", (string) $str);

        return trim((string) $str);
    }

    /**
     * Replaces text emoticons with Emoji versions
     */
    protected function _parseEmoticons(string $str): string
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

        $match = implode('|', array_map(fn ($str): string => preg_quote($str, '/'), array_keys($map)));
        $regex = "/([^a-z\\/&]|^)($match)([^a-z\\/]|$)/m";

        return preg_replace_callback($regex, fn ($match): string => $match[1] . $map[$match[2]] . $match[3], $str);
    }

    /**
     * Passes a string through the Textile parser
     */
    protected function _parseTextile(string $str, bool $escape = true): string
    {
        error_reporting(E_ALL ^ E_DEPRECATED); // Temporarily ignore deprecations because this lib is incompatible
        $tex = new \Netcarver\Textile\Parser('html5');
        $tex->setDimensionlessImages(true);
        $tex->setRestricted($escape);
        return $tex->parse($str);
    }

    /**
     * Passes a string through the Markdown parser
     */
    protected function _parseMarkdown(string $str): string
    {
        $md = new GithubFlavoredMarkdownConverter([
            'renderer' => [
                'soft_break' => '<br>',
            ],
        ]);
        return $md->convert($str)->getContent();
    }

    /**
     * Get a human-readable file size
     */
    public function formatFilesize(int $filesize): string
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
     */
    public function gravatar(string $email, int $size = 80): string
    {
        $f3 = \Base::instance();
        $rating = $f3->get("gravatar.rating") ?: "pg";
        $default = $f3->get("gravatar.default") ?: "mm";
        return "https://gravatar.com/avatar/" . md5(strtolower($email ?? '')) .
                "?s=" . intval($size) .
                "&d=" . urlencode((string) $default) .
                "&r=" . urlencode((string) $rating);
    }

    /**
     * Get UTC time offset in seconds
     */
    public function timeoffset(): int
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
     */
    public function utc2local(int|string|null $timestamp = null): int|float
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
     */
    public function lang(): string
    {
        $f3 = \Base::instance();
        $langs = $f3->split($f3->get("LANGUAGE"));
        return $langs[0] ?? $f3->get("FALLBACK", "en");
    }
}
