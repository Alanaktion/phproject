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

		$img = new \Image($file->disk_filename, null, $f3->get("ROOT") . "/");
		$img->resize($params["size"], $params["size"]);

		if($params["format"] == "jpg") {
			$params["format"] = "jpeg";
		}
		$img->render($params["format"]);
	}

	public function file($f3, $params) {
		$file = new \Model\Issue\File();
		$file->load($params["id"]);

		if(!$file->id) {
			$f3->error(404);
			return;
		}

		header("Content-Type: " . $file->content_type);
		header("Content-Length: " . filesize($file->disk_filename));
		readfile($file->disk_filename);
	}

}
