<?php

namespace Controller;

class Issues extends Base {

	public function index($f3, $params) {
		$this->_requireLogin();

		$issues = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user", null, 3600);

		// Filter issue listing by URL parameters
		$filter = array();
		$args = $f3->get("GET");
		if(!empty($args["type"])) {
			$filter["type_id"] = intval($args["type"]);
		}
		if(isset($args["owner"])) {
			$filter["owner_id"] = intval($args["owner"]);
		}

		// Build SQL string to use for filtering
		$filter_str = "";
		foreach($filter as $i => $val) {
			$filter_str .= "$i = '$val' AND ";
		}
		$filter_str .= "deleted_date IS NULL";

		// Load type if a type_id was passed
		if(!empty($args["type"])) {
			$type = new \Model\Issue\Type();
			$type->load(array("id = ?", $args["type"]));
			if($type->id) {
				$f3->set("title", $type->name . "s");
				$f3->set("type", $type->cast());
			}
		}

		$f3->set("issues", $issues->paginate(0, 50, $filter_str));
		echo \Template::instance()->render("issues/index.html");
	}

	public function add($f3, $params) {
		$this->_requireLogin();

		if($f3->get("PARAMS.type")) {
			$type_id = $f3->get("PARAMS.type");
		} else {
			$type_id = 1;
		}

		$type = new \Model\Issue\Type();
		$type->load(array("id=?", $type_id));

		if(!$type->id) {
			$f3->error(500, "Issue type does not exist");
			return;
		}

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL", array("order" => "name ASC")));

		$f3->set("title", "New " . $type->name);
		$f3->set("type", $type->cast());

		echo \Template::instance()->render("issues/edit.html");
	}

	public function edit($f3, $params) {
		$this->_requireLogin();

		$issue = new \Model\Issue();
		$issue->load(array("id=?", $f3->get("PARAMS.id")));

		if(!$issue->id) {
			$f3->error(404, "Issue does not exist");
			return;
		}

		$type = new \Model\Issue\Type();
		$type->load(array("id=?", $issue->type_id));

		$users = new \Model\User();
		$f3->set("users", $users->paginate(0, 1000, "deleted_date IS NULL", array("order" => "name ASC")));

		$f3->set("title", "Edit #" . $issue->id);
		$f3->set("issue", $issue->cast());
		$f3->set("type", $type->cast());

		echo \Template::instance()->render("issues/edit.html");
	}

	public function save($f3, $params) {
		$user_id = $this->_requireLogin();

		$post = array_map("trim", $f3->get("POST"));

		$issue = new \Model\Issue();
		if(!empty($post["id"])) {

			// Updating existing issue.
			$issue->load(array("id = ?", $post["id"]));
			if($issue->id) {

				// Log changes
				$update = new \Model\Issue\Update();
				$update->issue_id = $issue->id;
				$update->user_id = $user_id;
				$update->created_date = now();
				$update->save();

				// Diff contents and save what's changed.
				foreach($post as $i=>$val) {
					if($issue->$i != $val) {
						$update_field = new \Model\Issue\Update\Field();
						$update_field->issue_update_id = $update->id;
						$update_field->field = $i;
						$update_field->old_value = $issue->$i;
						$update_field->new_value = $val;
						$update_field->save();
						if(empty($val)) {
							$issue->$i = null;
						} else {
							$issue->$i = $val;
						}
					}
				}

				$issue->save();
				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(404, "This issue does not exist.");
			}

		} elseif($f3->get("POST.name")) {

			// Creating new issue.
			$issue->author_id = $f3->get("user.id");
			$issue->type_id = $post["type_id"];
			$issue->created_date = now();
			$issue->name = $post["name"];
			$issue->description = $post["description"];
			$issue->owner_id = $post["owner_id"];
			$issue->due_date = date("Y-m-d", strtotime($post["due_date"]));
			if(!empty($post["parent_id"])) {
				$issue->parent_id = $post["parent_id"];
			}
			$issue->save();

			if($issue->id) {
				$f3->reroute("/issues/" . $issue->id);
			} else {
				$f3->error(500, "An error occurred saving the issue.");
			}

		}
	}

	public function single($f3, $params) {
		$user_id = $this->_requireLogin();

		$issue = new \Model\Issue();
		$issue->load(array("id=? AND deleted_date IS NULL", $f3->get("PARAMS.id")));
                
                $issue = new \Model\Issue();
		$issue->load(array("id=?", $f3->get("PARAMS.id")));

		if(!$issue->id) {
			$f3->error(404);
			return;
		}

		// Run actions if passed
		$post = $f3->get("POST");
		if(!empty($post)) {
			switch($post["action"]) {
				case "comment":
					$comment = new \Model\Issue\Comment();
					$comment->user_id = $user_id;
					$comment->issue_id = $issue->id;
					$comment->text = $post["text"];
					$comment->created_date = date("Y-m-d H:i:s");
					$comment->save();
					if($f3->get("AJAX")) {
						echo json_encode(
							array(
								"id" => $comment->id,
								"text" => $comment->text,
								"date_formatted" => date("D, M j, Y \\a\\t g:ia"),
								"user_name" => $f3->get('user.name'),
								"user_username" => $f3->get('user.username'),
								"user_email" => $f3->get('user.email'),
								"user_email_md5" => md5(strtolower($f3->get('user.email'))),
							)
						);
						return;
					}
					break;
			}
		}

		$f3->set("title", $issue->name);

		$author = new \Model\User();
		$author->load(array("id=?", $issue->author_id));

		$f3->set("issue", $issue->cast());
		$f3->set("author", $author->cast());

		$comments = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_comment_user", null, 3600);
		$f3->set("comments", $comments->paginate(0, 100, array("issue_id = ?", $issue->id), array("order" => "created_date ASC")));

		echo \Template::instance()->render("issues/single.html");
	}

	public function single_history($f3, $params) {
		$user_id = $this->_requireLogin();

		// Build updates array
		$updates_array = array();
		$update_model = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_update_user", null, 3600);
		$updates = $update_model->paginate(0, 100, array("issue_id = ?", $params["id"]), array("order" => "created_date ASC"));
		foreach($updates["subset"] as $update) {
			$update_field_model = new \Model\Issue\Update\Field();
			$update_field_result = $update_field_model->paginate(0, 100, array("issue_update_id = ?", $update["id"]));
			$update_array = $update->cast();
			$update_array["text"] = "Soon.";
			$updates_array[] = $update_array;
		}

		$f3->set("updates", $updates_array);

		echo \Template::instance()->render("issues/single/history.html");
	}

	public function single_related($f3, $params) {
		$user_id = $this->_requireLogin();
		$issues = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user", null, 3600);
		$f3->set("issues", $issues->paginate(0, 100, array("parent_id = ? AND deleted_date IS NULL", $params["id"])));
		echo \Template::instance()->render("issues/single/related.html");
	}

	public function single_delete($f3, $params) {
		$this->_requireLogin();

		$issue = new \Model\Issue();
		$issue->load(array("id = ?", $params["id"]));
		if($f3->get("POST.id")) {
			$issue->delete();
			$f3->reroute("/issues");
		} else {
			$f3->set("issue", $issue->cast());
			echo \Template::instance()->render("issues/delete.html");
		}
	}

	public function search($f3, $params) {
		$query = "%" . $f3->get("GET.q") . "%";
		$issues = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_user", null, 3600);
		$results = $issues->paginate(0, 50, array("name LIKE ? OR description LIKE ? AND deleted_date IS NULL", $query, $query), array("order" => "created_date ASC"));
		$f3->set("issues", $results);
		echo \Template::instance()->render("issues/search.html");
	}
        
        public function upload($f3, $params) {
                $user_id = $this->_requireLogin();
                $issue = new \Model\Issue();
		$issue->load(array("id=? AND deleted_date IS NULL", $f3->get("POST.issue_id")));
		if(!$issue->id) {
			$f3->error(404);
			return;
		}
                
                
                $web = \Web::instance();
                
                
                
                
                $f3->set('UPLOADS','uploads/'.date("Y")."/".date("m")."/"); // don't forget to set an Upload directory, and make it writable!
                if(!file_exists($f3->get("UPLOADS"))) {
                    mkdir($f3->get("UPLOADS"), 0777, true);
                }
                $overwrite = false; // set to true, to overwrite an existing file; Default: false
                $slug = true; // rename file to filesystem-friendly version
               
                $files = $web->receive(function($file){
                        
                        //var_dump($file);
                        /* looks like:
                          array(5) {
                              ["name"] =>     string(19) "somefile.png"
                              ["type"] =>     string(9) "image/png"
                              ["tmp_name"] => string(14) "/tmp/php2YS85Q"
                              ["error"] =>    int(0)
                              ["size"] =>     int(172245)
                            }
                        */
                        // $file['name'] already contains the slugged name now

                        // maybe you want to check the file size
                        if($file['size'] > (2 * 1024 * 1024)) // if bigger than 2 MB
                            return false; // this file is not valid, return false will skip moving it
                            //
                        //see if a file already exists witih that name
//                        if(file_exists($f3->get("UPLOADS").$file["name"])) { 
//                            $f3->set('ERROR',"A file already exists with that name.");
//                            return false;
//                        }
                        
                        
//                        $newfile = new \Model\Issue\File();
//                        $newfile->issue_id = $issue->id;
//                        $newfile->user_id = $user_id;
//                        $newfile->filename = $file['name'];
//                        $newfile->disk_filename = $file['name'];
//                        $newfile->disk_directory = $f3->get("UPLOADS");
//                        $newfile->filesize = $file['size'];
//                        $newfile->content_type = $file['type'];
//                        $newfile->digest = md5_file($f3->get("UPLOADS") . $file['name']); 
//                        $newfile->created_date = date("Y-m-d H:i:s");
//                        $newfile->save();
                        
                        //STILL NEED:
                        //Name handling
                        
                        // everything went fine, hurray!
                        return true; // allows the file to be moved from php tmp dir to your defined upload dir
                    },
                    $overwrite,
                    $slug
                );
                print "<pre>\r\n With Receive\r\n";    
                print_r ($files);
                echo "\r\n with _FILES\r\n";
                print_r ($_FILES);
		print "</pre><br />";
                echo "Web receive is not going to work, will have to just use mpve_uploaded_file()";
                //$f3->reroute('/issues/'.$issue->id);
                
        }

}
