<?php

namespace Controller;

class Files extends \Controller {

	/**
	 * Forces the framework to use the local filesystem cache method if possible
	 */
	protected function _useFileCache() {
		$f3 = \Base::instance();
		$f3->set("CACHE", "folder=" . $f3->get("TEMP") . "cache/");
	}

	/**
	 * Send a file to the browser
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
		$hash = $f3->hash($f3->get('VERB') . " " . $f3->get('URI')) . ".thm";
		if($cache->exists($hash, $data)) {
			header("Content-type: image/" . $params["format"]);
			echo $data;
			return;
		}

		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id) {
			$f3->error(404);
			return;
		}

		$fg = 0x000000;
		$bg = 0xFFFFFF;

		// Generate thumbnail of image file
		if(substr($file->content_type, 0, 6) == "image/") {
			if(is_file($file->disk_filename)) {
				$img = new \Helper\Image($file->disk_filename);
				$hide_ext = true;
			} else {
				$protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0";
				header($protocol . " 404 Not Found");
				$img = new \Helper\Image("img/404.png");
			}
			$img->resize($params["size"], $params["size"]);

			$fg = 0xFFFFFF;
			$bg = 0x000000;
		}

		// Generate thumbnail of text contents
		elseif(substr($file->content_type, 0, 5) == "text/") {

			// Get first 2KB of file
			$fh = fopen($file->disk_filename, "r");
			$str = fread($fh, 2048);
			fclose($fh);

			// Replace tabs with spaces
			$str = str_replace("\t", "  ", $str);

			$img = new \Helper\Image();
			$img->create($params["size"], $params["size"]);
			$img->fill(0xFFFFFF);
			$img->text($str, round(0.05 * $params["size"]), 0, round(0.03 * $params["size"]), round(0.03 * $params["size"]), 0x777777);

			// Show file type icon if available
			if($file->content_type == "text/csv" || $file->content_type == "text/tsv") {
				$icon = new \Image("img/mime/table.png");
				$img->overlay($icon);
			}
		}

		// Generate thumbnail of MS Office document
		elseif(extension_loaded("zip")
			&& $file->content_type == "application/vnd.openxmlformats-officedocument.wordprocessingml.document") {
			$zip = zip_open($file->disk_filename);
			while(($entry = zip_read($zip)) !== false) {
				if(preg_match("/word\/media\/image[0-9]+\.(png|jpe?g|gif|bmp|dib)/i", zip_entry_name($entry))) {
					$idata = zip_entry_read($entry, zip_entry_filesize($entry));
					$img = new \Helper\Image();
					$img->load($idata);
					break;
				}
			}

			if(!isset($img)) {
				$img = new \Helper\Image("img/mime/base.png");
			}
			$img->resize($params["size"], $params["size"]);
		}

		// Use generic file icon if type is not supported
		else {
			$img = new \Helper\Image("img/mime/base.png");
			$img->resize($params["size"], $params["size"]);
		}

		// Render file extension over image
		if(empty($hide_ext)) {
			$ext = strtoupper(pathinfo($file->disk_filename, PATHINFO_EXTENSION));
			$img->text($ext, $params["size"]*0.125, 0, round(0.05 * $params["size"]), round(0.05 * $params["size"]), $bg);
			$img->text($ext, $params["size"]*0.125, 0, round(0.05 * $params["size"]) - 1, round(0.05 * $params["size"]) - 1, $fg);
		}

		// Render and cache image
		$data = $img->dump($params["format"]);
		$cache->set($hash, $data, $f3->get("cache_expire.attachments"));

		// Output image
		header("Content-type: image/" . $params["format"]);
		echo $data;

	}

	/**
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
			// Don't force download on image and plain text files
			// Eventually I'd like to have previews of files some way (more than
			// the existing thumbnails), but for now this is how we do it - Alan
			$force = false;
		}

		if(!$this->_sendFile($file->disk_filename, $file->content_type, $file->filename, $force)) {
			$f3->error(404);
		}
	}

}
