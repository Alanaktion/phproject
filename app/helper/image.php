<?php

namespace Helper;

class Image extends \Image {

	protected $lastData;

	static $mimeMap = array(
		"image" => array(
			"image/jpeg",
			"image/png",
			"image/gif",
			"image/bmp",
		),
		"text" => array(
			// @todo: Use these values to generate text file thumbnails
			"text/plain",
			"text/tsv",
			"text/csv",
		),
		"icon" => array(
			"audio/.+" => "_audio",
			"application/.*zip" => "_archive",
			"application/x-php" => "_code",
			"(application|text)/xml" => "_code",
			"text/html" => "_code",
			"image/.+" => "_image",
			"application/x-photoshop" => "_image",
			"video/.+" => "_video",
			"application/.*pdf" => "pdf",
			"text/[ct]sv" => "csv",
			"text/.+-separated-values" => "csv",
			"text/.+" => "txt",
			"application/sql" => "txt",
			"application/vnd\.oasis\.opendocument\.graphics" => "odg",
			"application/vnd\.oasis\.opendocument\.spreadsheet" => "ods",
			"application/vnd\.oasis\.opendocument\.presentation" => "odp",
			"application/vnd\.oasis\.opendocument\.text" => "odt",
			"application/(msword|vnd\.(ms-word|openxmlformats-officedocument\.wordprocessingml.+))" => "doc",
			"application/(msexcel|vnd\.(ms-excel|openxmlformats-officedocument\.spreadsheetml.+))" => "xls",
			"application/(mspowerpoint|vnd\.(ms-powerpoint|openxmlformats-officedocument\.presentationml.+))" => "ppt",
		)
	);

	/**
	 * Get an icon name by MIME type
	 *
	 * Returns "_blank" when no icon matches
	 *
	 * @param  string $contentType
	 * @return string
	 */
	static function mimeIcon($contentType) {
		foreach (self::$mimeMap["icon"] as $regex=>$name) {
			if (preg_match("@^" . $regex . "$@i", $contentType)) {
				return $name;
			}
		}
		return "_blank";
	}

	/**
	 * Get the last GD return value, generally from imagettftext
	 * @return mixed lastData
	 */
	function getLastData() {
		return $this->lastData;
	}

	/**
	 * Create a new blank canvase
	 * @param  int $width
	 * @param  int $height
	 * @return Image
	 */
	function create($width, $height) {
		$this->data = imagecreatetruecolor($width, $height);
		imagesavealpha($this->data, true);
	}

	/**
	 * Render a line of text
	 * @param  string  $text
	 * @param  float   $size
	 * @param  integer $angle
	 * @param  integer $x
	 * @param  integer $y
	 * @param  hex     $color
	 * @param  string  $font
	 * @param  hex     $overlay_color
	 * @param  float   $overlay_transparency
	 * @param  integer $overlay_padding
	 * @return Image
	 */
	function text($text, $size = 9.0, $angle = 0, $x = 0, $y = 0, $color = 0x000000, $font = "opensans-regular.ttf",
		$overlay_color = null, $overlay_transparency = 0.5, $overlay_padding = 2
	) {
		$f3 = \Base::instance();

		$font = $f3->get("ROOT") . "/fonts/" . $font;
		if(!is_file($font)) {
			$f3->error(500, "Font file not found");
			return false;
		}

		$color = $this->rgb($color);
		$color_id = imagecolorallocate($this->data, $color[0], $color[1], $color[2]);

		$bbox = imagettfbbox($size, $angle, $font, "M");
		$y += $bbox[3] - $bbox[5];

		if(!is_null($overlay_color)) {
			$overlay_bbox = imagettfbbox($size, $angle, $font, $text);
			$overlay_color = $this->rgb($overlay_color);
			$overlay_color_id = imagecolorallocatealpha($this->data, $overlay_color[0], $overlay_color[1], $overlay_color[2], $overlay_transparency * 127);
			imagefilledrectangle(
				$this->data,
				$x - $overlay_padding,
				$y - $overlay_padding,
				$x + $overlay_bbox[2] - $overlay_bbox[0] + $overlay_padding,
				$y + $overlay_bbox[3] - $overlay_bbox[5] + $overlay_padding,
				$overlay_color_id
			);
		}

		$this->lastData = imagettftext($this->data, $size, $angle, $x, $y, $color_id, $font, $text);
		return $this->save();
	}

	/**
	 * Fill image with a solid color
	 * @param  hex $color
	 * @return Image
	 */
	function fill($color = 0x000000) {
		$color = $this->rgb($color);
		$color_id = imagecolorallocate($this->data, $color[0], $color[1], $color[2]);
		imagefill($this->data, 0, 0, $color_id);
		return $this->save();
	}

}
