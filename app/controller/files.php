<?php

namespace Controller;

class Files extends Base {

	public function thumb($f3, $params) {
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
			} else {
				http_response_code(404);
				$img = new \Helper\Image("img/404.png", null, $f3->get("ROOT") . "/");
			}
			$img->resize($params["size"], $params["size"]);

			$fg = 0xFFFFFF;
			$bg = 0x000000;

			// Ensure proper content-type for JPEG images
			if($params["format"] == "jpg") {
				$params["format"] = "jpeg";
			}
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
		$ext = strtoupper(pathinfo($file->disk_filename, PATHINFO_EXTENSION));
		$img->text($ext, 16, 0, 3, 4, $bg);
		$img->text($ext, 16, 0, 2, 3, $fg);

		$img->render($params["format"]);
	}

	public function avatar($f3, $params) {
		$user = new \Model\User();
		$user->load($params["id"]);

		if($user->avatar_filename && is_file($f3->get("ROOT") . "/uploads/avatars/" . $user->avatar_filename)) {

			// Use local file
			$img = new \Image($user->avatar_filename, null, $f3->get("ROOT") . "/uploads/avatars/");
			$img->resize($params["size"], $params["size"]);

			// Ensure proper content-type for JPEG images
			if($params["format"] == "jpg") {
				$params["format"] = "jpeg";
			}
			$img->render($params["format"]);

		} else {

			// Use Gravatar if user does not have an avatar
			// Note: this should rarely be used, as the URL for Gravatars should be used directly in most cases
			header("Content-type: image/png");
			readfile("http:" . gravatar($user->email, $params["size"]));

		}
	}

	public function file($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id) {
			$f3->error(404);
			return;
		}

		if(!\Web::instance()->send($f3->get("ROOT") . "/" . $file->disk_filename)) {
			$f3->error(404);
		}
	}

}
