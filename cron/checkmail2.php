<?php
require_once "base.php";
$log = new \Log("checkmail.log");

$imap = $f3->get("imap");
if(!isset($imap["hostname"])) {
	throw new Exception("No IMAP hostname specified in configuration");
}

$inbox = imap_open($imap['hostname'],$imap['username'],$imap['password'], OP_READONLY);
if($inbox === false) {
	$err = 'Cannot connect to IMAP: ' . imap_last_error();
	$log->write($err);
	throw new Exception($err);
}

// @todo: replace search with 'ALL UNSEEN'
$emails = imap_search($inbox,'SUBJECT "testing" SEEN SINCE "15 July 2015"');
if(!$emails) {
	exit;
}

foreach($emails as $msg_number) {
	$header = imap_headerinfo($inbox, $msg_number);
	$structure = imap_fetchstructure($inbox, $msg_number);

	$text = "";
	$attachments = array();

	// Load message parts
	foreach($structure->parts as $part_number=>$part) {

		// Handle plaintext
		if($part->type === 0 && !trim($text)) {
			$text = imap_fetchbody($inbox, $msg_number, $part_number);

			// Decode body
			if($part->encoding == 4) {
				$text = imap_qprint($text);
			} elseif($part->encoding == 3) {
				$text = imap_base64($text);
			}

			// Un-HTML an HTML part
			if($part->ifsubtype && $part->subtype == 'HTML') {
				$text = html_entity_decode(strip_tags($text));
			}

		}

		// Handle multipart
		elseif($part->type == 1 && !trim($text)) {
			foreach($part->parts as $multipart_number=>$multipart) {
				$text = imap_fetchbody($inbox, $msg_number, ($part_number + 1) . '.' . $multipart_number);

				// Decode body
				if($multipart->encoding == 4) {
					$text = imap_qprint($text);
				} elseif($multipart->encoding == 3) {
					$text = imap_base64($text);
				}

				// Un-HTML an HTML part
				if($multipart->ifsubtype && $multipart->subtype == 'HTML') {
					$text = html_entity_decode(strip_tags($text));
				}
			}
		}

		// Handle attachments
		elseif($part->type > 1 && $part->ifdisposition && $part->disposition == 'ATTACHMENT') {

			// Get filename
			$filename = '';
			if($part->ifdparameters) {
				foreach($part->dparameters as $param) {
					if($param->attribute == 'FILENAME') {
						$filename = $param->value;
						break;
					}
				}
			} elseif($part->ifparameters) {
				foreach($part->parameters as $param) {
					if($param->attribute == 'NAME') {
						$filename = $param->value;
						break;
					}
				}
			}

			$bodystruct = imap_bodystruct($inbox, $msg_number, $part_number);
			// print_r($bodystruct);

			// Store attachment metadata
			$attachments[] = array(
				'part_number' => $part_number,
				'filename' => $filename,
				'size' => $part->bytes,
				'encoding' => $part->encoding,
			);

		}
	}

	echo $text;
	exit;

	$from = $header->from[0]->mailbox . "@" . $header->from[0]->host;
	$from_user = new \Model\User;
	$from_user->load(array('email = ? AND deleted_date IS NULL', $from));
	if(!$from_user->id) {
		$log->write('Unable to find user for ' . $header->subject);
		continue;
	}

	$to_user = new \Model\User;
	foreach($header->to as $to_email) {
		$to = $to_email->mailbox . "@" . $to_email->host ;
		$user->load(array('email = ? AND deleted_date IS NULL', $to));
		if(!empty($user->id)) {
			$owner = $to_user->id;
			break;
		} else {
			$owner = $from_user->id;
		}
	}

	// Find issue IDs in subject
	preg_match("/\[#([0-9]+)\] -/", $header->subject, $matches);

	// Get issue instance
	$issue = new \Model\Issue;
	if(!empty($matches[0])) {
		$issue->load(intval($matches[0]));
	}
	if(!$issue->id) {
		$subject = trim(preg_replace("/^((Re|Fwd?):\s)*/i", "", $header->subject));
		$issue->load(array('name=? AND deleted_date IS NULL AND closed_date IS NULL', $subject));
	}

	if($issue->id) {
		if(trim($text)) {
			$comment = \Model\Issue\Comment::create(array(
				'user_id' => $from_user->id,
				'issue_id' => $issue->id,
				'text' => $text,
			));
			$log->write("Added comment {$comment->id} on issue {$issue->id}");
		}
	} else {
		$issue = \Model\Issue::create(array(
			'name' => $header->subject,
			'description' => $text,
			'author_id' => $from_user->id,
			'owner_id' => $owner,
			'type_id' => 1
		));
		$log->write("Created issue {$issue->id}");
	}

	// @todo: Add other recipients as watchers

	foreach($attachments as $item) {

		// Skip big files
		if($item['size'] > $f3->get("files.maxsize")) {
			continue;
		}

		// Load file contents
		$data = imap_fetchbody($inbox, $msg_number, $item['part_number']);

		// Decode contents
		if($item['encoding'] == 4) {
			$data = imap_qprint($data);
		} elseif($item['encoding'] == 3) {
			$data = imap_base64($data);
		}

		// Store file
		$dir = 'uploads/' . date('Y/m/');
		$item['filename'] = preg_replace("/[^A-Z0-9._-]/i", "_", $item['filename']);
		$disk_filename = $dir . time() . "_" . $item['filename'];
		file_put_contents($f3->get('ROOT') . '/' . $disk_filename, $data);

		// @todo: Find a way to parse the Content-type header from the message
		$file = \Model\Issue\File::create(array(
			"issue_id" => $issue->id,
			"user_id" => $from_user->id,
			"filename" => $item['filename'],
			"disk_directory" => $dir,
			"disk_filename" => $disk_filename,
			"filesize" => strlen($data),
			"content_type" => \Web::instance()->mime($item['filename']),
			"digest" => md5($data),
		));

		$log->write("Saved file {$file->id} on issue {$issue->id}");
	}

}
