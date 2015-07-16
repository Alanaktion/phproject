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

	print_r($header);print_r($structure);exit;

	$text = "";
	$attachments = array();

	// Load message parts
	foreach($structure->parts as $part_number=>$part) {
		if($part->type === 0 && !$text) {
			$text = imap_fetchbody($inbox, $msg_number, $part_number);

			// Decode body
			if($part->encoding == 4) {
				$text = imap_qprint($text);
			} elseif($part->encoding == 3) {
				$text = base64_decode($text);
			}

			// Un-HTML an HTML part
			if($part->ifsubtype && $part->subtype == 'HTML') {
				$text = html_entity_decode(strip_tags($text));
			}

		} elseif($part->type > 0 && $part->ifdisposition && $part->disposition == 'ATTACHMENT') {

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

			// Store attachment metadata
			$attachments[] = array(
				'part_number' => $part_number,
				'filename' => $filename,
				'size' => $part->bytes,
			);

		}
	}

	$from = $header->from[0]->mailbox . "@" . $header->from[0]->host;

	$from_user = new \Model\User();
	$from_user->load(array('email = ? AND deleted_date IS NULL', $from));
	if(!$from_user->id) {
		$log->write('Unable to find user for ' . $hedaer->subject);
		continue;
	}

	$to_user = new \Model\User();
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

}
