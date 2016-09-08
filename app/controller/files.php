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

		// Generate thumbnail of image file
		/* @todo: Replace with hacky imagefillrectangle and imagecopyresampled
			code to render a nice _image.svg-style frame around the thumbnail */
		if(is_file($file->disk_filename)) {
			$img = new \Helper\Image($file->disk_filename);
			$hide_ext = true;
		} else {
			header("Content-Type: image/svg+xml");
			readfile("img/mime/96/_404.svg");
			return;
		}
		$img->resize($params["size"], $params["size"]);

		// Render and cache image
		$data = $img->dump($params["format"]);
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
