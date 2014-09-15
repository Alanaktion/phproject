<?php

namespace Controller;

class Files extends Base {

	/**
	 * Forces the framework to use the local filesystem cache method if possible
	 */
	protected function _useFileCache() {
		$f3 = \Base::instance();
		$f3->set("CACHE", "folder=" . $f3->get("TEMP") . "cache/");
	}

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
		if(substr($file->content_type, 0, 5) == "image") {
			if(is_file($f3->get("ROOT") . "/" . $file->disk_filename)) {
				$img = new \Helper\Image($file->disk_filename, null, $f3->get("ROOT") . "/");
				$hide_ext = true;
			} else {
				$protocol = isset($_SERVER["SERVER_PROTOCOL"]) ? $_SERVER["SERVER_PROTOCOL"] : "HTTP/1.0";
				header($protocol . " 404 Not Found");
				$img = new \Helper\Image("img/404.png", null, $f3->get("ROOT") . "/");
			}
			$img->resize($params["size"], $params["size"]);

			$fg = 0xFFFFFF;
			$bg = 0x000000;
		}

		// Generate thumbnail of text contents
		elseif(substr($file->content_type, 0, 4) == "text") {

			// Get first 2KB of file
			$fh = fopen($f3->get("ROOT") . "/" . $file->disk_filename, "r");
			$str = fread($fh, 2048);
			fclose($fh);

			// Replace tabs with spaces
			$str = str_replace("\t", "  ", $str);

			$img = new \Helper\Image();
			$img->create($params["size"], $params["size"]);
			$img->fill(0xFFFFFF);
			$img->text($str, 5, 0, 2, 2, 0x777777);

			// Show file type icon if available
			if($file->content_type == "text/csv" || $file->content_type == "text/tsv") {
				$icon = new \Image("img/mime/table.png", null, $f3->get("ROOT") . "/");
				$img->overlay($icon);
			}
		}

		// Use generic file icon if type is not supported
		else {
			$img = new \Helper\Image("img/mime/base.png", null, $f3->get("ROOT") . "/");
			$img->resize($params["size"], $params["size"]);
		}

		// Render file extension over image
		if(empty($hide_ext)) {
			$ext = strtoupper(pathinfo($file->disk_filename, PATHINFO_EXTENSION));
			$img->text($ext, 12, 0, 5, 5, $bg);
			$img->text($ext, 12, 0, 4, 4, $fg);
		}

		// Render and cache image
		$data = $img->dump($params["format"]);
		$cache->set($hash, $data, $f3->get("cache_expire.attachments"));

		// Output image
		header("Content-type: image/" . $params["format"]);
		echo $data;

	}

	public function avatar($f3, $params) {
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

		$user = new \Model\User();
		$user->load($params["id"]);

		if($user->avatar_filename && is_file($f3->get("ROOT") . "/uploads/avatars/" . $user->avatar_filename)) {

			// Use local file
			$img = new \Image($user->avatar_filename, null, $f3->get("ROOT") . "/uploads/avatars/");
			$img->resize($params["size"], $params["size"]);

			// Render and cache image
			$data = $img->dump($params["format"]);
			$cache->set($hash, $data, $f3->get("cache_expire.attachments"));

			// Output image
			header("Content-type: image/" . $params["format"]);
			echo $data;

		} else {

			// Remove avatar from user if site is not in debug mode
			if($user->avatar_filename && !$f3->get("DEBUG")) {
				$user->avatar_filename = null;
				$user->save();
			}

			// Load image from Gravatar
			header("Content-type: image/" . $params["format"]);
			$data = $img->dump($params["format"]);
			$cache->set($hash, $data, $f3->get("cache_expire.attachments"));

			header("Content-type: image/png");
			$file = file_get_contents("http:" . gravatar($user->email, $params["size"]));
			echo $file;

		}
	}

	public function file($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id) {
			$f3->error(404);
			return;
		}

		$force = true;
		if(substr($file->content_type, 0, 5) == "image") {
			// Don't force download on image files
			$force = false;
		}

		if(!\Web::instance()->send($f3->get("ROOT") . "/" . $file->disk_filename, null, 0, $force)) {
			$f3->error(404);
		}
	}

}
