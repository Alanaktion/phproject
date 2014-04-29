<?php
/*
 * Wiki.php - Adapted from Wiky.php for Phproject
 *
 * Original Wiky.php details:
 *
 * Wiky.php - A tiny PHP "library" to convert Wiki Markup language to HTML
 * Author: Toni Lähdekorpi <toni@lygon.net>
 *
 * Code usage under any of these licenses:
 * Apache License 2.0, http://www.apache.org/licenses/LICENSE-2.0
 * Mozilla Public License 1.1, http://www.mozilla.org/MPL/1.1/
 * GNU Lesser General Public License 3.0, http://www.gnu.org/licenses/lgpl-3.0.html
 * GNU General Public License 2.0, http://www.gnu.org/licenses/gpl-2.0.html
 * Creative Commons Attribution 3.0 Unported License, http://creativecommons.org/licenses/by/3.0/
 */

namespace Helper;

class Wiki extends \Prefab {
	private $patterns, $replacements;

	public function __construct($analyze = false) {
		$this->patterns=array(
			// Headings
			"/^==== (.+?) ====$/m", // Subsubheading
			"/^=== (.+?) ===$/m",   // Subheading
			"/^== (.+?) ==$/m",     // Heading

			// Formatting
			"/\'\'\'\'\'(.+?)\'\'\'\'\'/s", // Bold-italic
			"/\'\'\'(.+?)\'\'\'/s", // Bold
			"/\'\'(.+?)\'\'/s", // Italic

			// Special
			"/^----+(\s*)$/m", // Horizontal line
			"/\[\[(file|img):((ht|f)tp(s?):\/\/(.+?))( (.+))*\]\]/i", // (File|img):(http|https|ftp) aka image
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))( (.+))\]/i", // Other urls with text
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))\]/i", // Other urls without text

			// Indentations
			"/[\n\r]: *.+([\n\r]:+.+)*/", // Indentation first pass
			"/^:(?!:) *(.+)$/m", // Indentation second pass
			"/([\n\r]:: *.+)+/", // Subindentation first pass
			"/^:: *(.+)$/m", // Subindentation second pass

			// Ordered list
			"/[\n\r]?#.+([\n|\r]#.+)+/", // First pass, finding all blocks
			"/[\n\r]#(?!#) *(.+)(([\n\r]#{2,}.+)+)/", // List item with sub items of 2 or more
			"/[\n\r]#{2}(?!#) *(.+)(([\n\r]#{3,}.+)+)/", // List item with sub items of 3 or more
			"/[\n\r]#{3}(?!#) *(.+)(([\n\r]#{4,}.+)+)/", // List item with sub items of 4 or more

			// Unordered list
			"/[\n\r]?\*.+([\n|\r]\*.+)+/", // First pass, finding all blocks
			"/[\n\r]\*(?!\*) *(.+)(([\n\r]\*{2,}.+)+)/", // List item with sub items of 2 or more
			"/[\n\r]\*{2}(?!\*) *(.+)(([\n\r]\*{3,}.+)+)/", // List item with sub items of 3 or more
			"/[\n\r]\*{3}(?!\*) *(.+)(([\n\r]\*{4,}.+)+)/", // List item with sub items of 4 or more

			// List items
			"/^[#\*]+ *(.+)$/m", // Wraps all list items to <li/>

			// Clean up newlines
			"/(?:(?:\r\n|\r|\n)\s*){2}/s",

			// Newlines (TODO: make it smarter and so that it grouped paragraphs)
			"/^(?!<li|dd).+(?=(<a|strong|em|img)).+$/mi", // Ones with breakable elements (TODO: Fix this crap, the li|dd comparison here is just stupid)
			"/^[^><\n\r]+$/m", // Ones with no elements

		);
		$this->replacements=array(
			// Headings
			"<h3>$1</h3>",
			"<h2>$1</h2>",
			"<h1>$1</h1>",

			//Formatting
			"<strong><em>$1</em></strong>",
			"<strong>$1</strong>",
			"<em>$1</em>",

			// Special
			"<hr/>",
			"<img src=\"$2\" alt=\"$6\"/>",
			"<a href=\"$1\">$7</a>",
			"<a href=\"$1\">$1</a>",

			// Indentations
			"\n<dl>$0\n</dl>", // Newline is here to make the second pass easier
			"<dd>$1</dd>",
			"\n<dd><dl>$0\n</dl></dd>",
			"<dd>$1</dd>",

			// Ordered list
			"\n<ol>\n$0\n</ol>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",

			// Unordered list
			"\n<ul>\n$0\n</ul>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",

			// List items
			"<li>$1</li>",

			// Clean up newlines
			"\n\n",

			// Newlines
			"$0<br/>",
			"$0<br/>",

		);
		if($analyze) {
			foreach($this->patterns as $k=>$v) {
				$this->patterns[$k].="S";
			}
		}
	}

	public function parse($str) {
		if(empty($str)) {
			return false;
		}

		$str = htmlentities($str); // Clean HTML
		$str = str_replace(array("\r\n", "\r"), "\n", $str); // Normalize newlines
		$str = preg_replace($this->patterns, $this->replacements, $str); // Parse markup

		return $str;
	}

	public function test_tables() {
		$t =
"TESTYTESTY
|Main Category| Website|
| Sub Category| Q Error|
| Consultant Id | 2456 |
| Customer Id | 53061 |

TESTYTESTY

| Customer Name | Mary Lou Paulsen |
| Customer Email | idahofarmgirl@hotmail.com |
|How did it happen? | Q wouldn't add Bakery items |
|Original Order # | n/a |
TESTYTESTY";

		$t = str_replace(array("\r\n", "\r"), "\n", $t); // Normalize newlines

		$t = preg_replace("/^(?<=\|)(.*)\|(.*)(?=\|)$/m", "$1</td><td>$2", $t); // Surround cells with <td>s
		$t = preg_replace("/^\|(.*)\|$/m", "<tr><td>$1</td></tr>", $t); // Surround rows with <tr>s
		$t = preg_replace("/(?<!\>|\n)\n(\<tr\>.+\<\/tr\>\n)+(?!\<|\n)/s", "\n<table>\n$1</table>\n", $t); // Surround tables with <table>s

		echo "<pre>" . htmlentities($t) . "</pre>";
	}
}
