<?php

namespace Controller;

class Files extends \Controller {

	/**
	 * Force the framework to use the local filesystem cache method if possible
	 */
	protected function _useFileCache() {
		$f3 = \Base::instance();
		$f3->set("CACHE", "folder=" . $f3->get("TEMP") . "cache/");
	}

	/**
	 * Send a file to the browser
	 *
	 * @param  string $file
	 * @param  string $mime
	 * @param  string $filename
	 * @param  bool   $force
	 * @return int|bool
	 */
	protected function _sendFile($file, $mime = "", $filename = "", $force = true) {
		if (!is_file($file)) {
			return FALSE;
		}

		$size = filesize($file);

		if(!$mime) {
			$mime = \Web::instance()->mime($file);
		}
		header("Content-Type: $mime");

		if ($force) {
			if(!$filename) {
				$filename = basename($file);
			}
			header("Content-Disposition: attachment; filename=\"$filename\"");
		}

		header("Accept-Ranges: bytes");
		header("Content-Length: $size");
		header("X-Powered-By: " . \Base::instance()->get("PACKAGE"));

		readfile($file);
		return $size;
	}

	/**
	 * GET /files/thumb/@size-@id.@format
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function thumb($f3, $params) {
		$this->_useFileCache();
		$cache = \Cache::instance();

		// Ensure proper content-type for JPEG images
		if($params["format"] == "jpg") {
			$params["format"] = "jpeg";
		}

		// Output cached image if one exists
		if($f3->get("DEBUG") < 2) {
			$hash = $f3->hash($f3->get('VERB') . " " . $f3->get('URI')) . ".thm";
			if($cache->exists($hash, $data)) {
				header("Content-type: image/" . $params["format"]);
				echo $data;
				return;
			}
		}

		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id) {
			$f3->error(404);
			return;
		}

		// Load 404 image if original file is missing
		if(!is_file($file->disk_filename)) {
			header("Content-Type: image/svg+xml");
			readfile("img/mime/96/_404.svg");
			return;
		}

		// Initialize thumbnail image
		$img = new \Helper\Image($file->disk_filename);

		// Render thumbnail directly if no alpha
		$alpha = (imagecolorat($img->data(), 0, 0) & 0x7F000000) >> 24;

		// 1.1 fits perfectly but crops shadow, so we compare width vs height
		$size = intval($params["size"]) ?: 96;
		if($alpha) {
			$thumbSize = $size;
		} elseif($img->width() > $img->height()) {
			$thumbSize = round($size / 1.1);
		} else {
			$thumbSize = round($size / 1.2);
		}

		// Resize thumbnail
		$img->resize($thumbSize, $thumbSize, false);
		$tw = $img->width();
		$th = $img->height();
		$ox = round(($size - $tw) / 2);
		$oy = round(($size - $th) / 2);
		$fs = round($size / 24);
		$fb = round($fs * 0.75);

		// Initialize frame image
		$frame = imagecreatetruecolor($size, $size);
		imagesavealpha($frame, true);
		$transparent = imagecolorallocatealpha($frame, 0, 0, 0, 127);
		imagefill($frame, 0, 0, $transparent);

		if(!$alpha) {
			// Draw drop shadow
			$cs = imagecolorallocatealpha($frame, 0, 0, 0, 120);
			imagefilledrectangle($frame, $ox - $fb, $size - $oy + $fb, $size - $ox + $fb, $size - $oy + round($fb * 1.5), $cs);
			imagefilledrectangle($frame, $ox - round($fb / 2), $size - $oy + $fb, $size - $ox + round($fb / 2), $size - $oy + round($fb * 1.625), $cs);
			imagefilledrectangle($frame, $ox, $size - $oy + $fb, $size - $ox, $size - $oy + round($fb * 2), $cs);

			// Draw frame
			$c0 = imagecolorallocatealpha($frame, 193, 193, 193, 16);
			imagefilledrectangle($frame, $ox - $fs, $oy - $fs, $size - $ox + $fs, $size - $oy + $fs, $c0);
			$c1 = imagecolorallocate($frame, 243, 243, 243);
			imagefilledrectangle($frame, $ox - $fb, $oy - $fb, $size - $ox + $fb, $size - $oy + $fb, $c1);
			$c2 = imagecolorallocate($frame, 230, 230, 230);
			imagefilledpolygon($frame, array(
				$size - $ox + $fb, $oy - $fb,
				$size - $ox + $fb, $size - $oy + $fb,
				$ox - $fb, $size - $oy + $fb,
			), 3, $c2);
		}

		// Copy thumbnail onto frame
		imagecopy($frame, $img->data(), $ox, $oy, 0, 0, $tw, $th);

		if(!$alpha) {
			// Draw inner shadow thumbnail
			$c3 = imagecolorallocatealpha($frame, 0, 0, 0, 100);
			imagerectangle($frame, $ox, $oy, $size - $ox, $size - $oy, $c3);
		}

		// Render and cache image
		ob_start();
		call_user_func_array('image' . $params["format"], array($frame));
		$data = ob_get_clean();
		if($f3->get("DEBUG") < 2) {
			$cache->set($hash, $data, $f3->get("cache_expire.attachments"));
		}

		// Output image
		header("Content-type: image/" . $params["format"]);
		echo $data;
	}

	/**
	 * GET /avatar/@size-@id.@format
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function avatar($f3, $params) {

		// Ensure proper content-type for JPEG images
		if($params["format"] == "jpg") {
			$params["format"] = "jpeg";
		}

		$user = new \Model\User();
		$user->load($params["id"]);

		if($user->avatar_filename && is_file("uploads/avatars/" . $user->avatar_filename)) {

			// Use local file
			$img = new \Image($user->avatar_filename, null, "uploads/avatars/");
			$img->resize($params["size"], $params["size"]);

			// Render and output image
			header("Content-type: image/" . $params["format"]);
			header("Cache-Control: private, max-age=" . (3600 / 2));
			$img->render($params["format"]);

		} else {

			// Send user to Gravatar
			header("Cache-Control: max-age=" . (3600 * 24));
			$f3->reroute($f3->get("SCHEME") . ":" . \Helper\View::instance()->gravatar($user->email, $params["size"]), true);

		}
	}

	/**
	 * GET /files/preview/@id
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function preview($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id || !is_file($file->disk_filename)) {
			$f3->error(404);
			return;
		}

		if(substr($file->content_type, 0, 5) == "image" || $file->content_type == "text/plain") {
			$this->_sendFile($file->disk_filename, $file->content_type, null, false);
			return;
		}

		if($file->content_type == "text/csv" || $file->content_type == "text/tsv") {
			$delimiter = ",";
			if($file->content_type == "text/tsv") {
				$delimiter = "\t";
			}
			$f3->set("file", $file);
			$f3->set("delimiter", $delimiter);
			$this->_render("issues/file/preview/table.html");
			return;
		}

		$f3->reroute("/files/{$file->id}/{$file->filename}");
	}

	/**
	 * GET /files/@id/@name
	 *
	 * @param \Base $f3
	 * @param array $params
	 * @throws \Exception
	 */
	public function file($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id) {
			$f3->error(404);
			return;
		}

		$force = true;
		if(substr($file->content_type, 0, 5) == "image" ||
			$file->content_type == "text/plain" ||
			$file->content_type == "application/pdf"
		) {
			$force = false;
		}

		if(!$this->_sendFile($file->disk_filename, $file->content_type, $file->filename, $force)) {
			$f3->error(404);
		}
	}

}
