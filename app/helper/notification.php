<?php

namespace Helper;

class Notification extends \Prefab {

	// Send an email to watchers with the comment body
	public function issue_comment($issue_id, $comment_id) {
		$f3 = \Base::instance();
		$log = new \Log("smtp.log");

		// Get issue and comment data
		$issue = new \Model\Issue();
		$issue->load($issue_id);
		$comment = new \Model\Custom("issue_comment_user");
		$comment->load($comment_id);

		// Get recipient list and remove current user
		$recipients = $this->_issue_watchers($issue_id);
		$recipients = array_diff($recipients, array($f3->get("user.email")));

		// Render message body
		$f3->set("issue", $issue);
		$f3->set("comment", $comment);
		$body = \Template::instance()->render("notification/comment.html");

		// Set up headers //SMTP NOT WORKING CORRECTLY
		//$smtp = $this->smtp_instance();
		//$smtp->set("Subject", $comment->user_name . " commented on #" . $issue->id . " " . $issue->name);
		//$smtp->set("From", $f3->get("mail.from"));
		//$smtp->set("Reply-to", $f3->get("mail.from"));
		//$smtp->set("Content-type", "text/html");


		$subject =  "[#" . $issue->id . "] - ".$comment->user_name . " commented on  " . $issue->name;
		// Send to recipients
		foreach($recipients as $recipient) {

			// To send HTML mail, the Content-type header must be set
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

			// Additional headers
			$headers .= 'To: '.$recipient . "\r\n";
			$headers .= 'From: '. $f3->get("mail.from") . "\r\n";
			//$smtp->set("To", $recipient);
			//$smtp->send($body);
			mail($recipient, $subject, $body, $headers);
			$log->write("Sent comment notification to: " . $recipient);
		}

		//$log->write($smtp->log());
	}

	// Send an email to watchers detailing the updated fields
	public function issue_update($issue_id, $update_id) {
		$f3 = \Base::instance();
		$log = new \Log("smtp.log");

		// Get issue and update data
		$issue = new \Model\Issue();
		$issue->load($issue_id);
		$f3->set("issue", $issue);
		$update = new \Model\Custom("issue_update_user");
		$update->load($update_id);

		// Avoid errors from bad calls
		if(!$issue->id || !$update->id) {
			return false;
		}

		$changes = new \Model\Issue\Update\Field();
		$f3->set("changes", $changes->find(array("issue_update_id = ?", $update->id)));

		// Get recipient list and remove current user
		$recipients = $this->_issue_watchers($issue_id);
		$recipients = array_diff($recipients, array($f3->get("user.email")));

		// Render message body
		$f3->set("issue", $issue);
		$f3->set("update", $update);
		$body = \Template::instance()->render("notification/update.html");

		// Set up headers
		//$smtp = $this->smtp_instance();
		//$smtp->set("Subject", "[#" . $issue->id . "] - " . $update->user_name . " updated  " . $issue->name);
		//$smtp->set("From", $f3->get("mail.from"));
		//$smtp->set("Reply-to", $f3->get("mail.from"));
		//$smtp->set("Content-type", "text/html");


		$subject =  "[#" . $issue->id . "] - ".$update->user_name . " updated  " . $issue->name;
		// Send to recipients
		foreach($recipients as $recipient) {

			// To send HTML mail, the Content-type header must be set
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

			// Additional headers
			$headers .= 'To: '.$recipient . "\r\n";
			$headers .= 'From: '. $f3->get("mail.from") . "\r\n";
			//$smtp->set("To", $recipient);
			//$smtp->send($body);
			mail($recipient, $subject, $body, $headers);
			$log->write("Sent update notification to: " . $recipient);
		}

		//$log->write($smtp->log());
	}

	// Send an email to watchers detailing the updated fields
	public function issue_create($issue_id) {
		// TODO: make this not use the update data :P
		return false; // exit early since it won't work yet.

		$f3 = \Base::instance();
		$log = new \Log("smtp.log");

		// Get issue and update data
		$issue = new \Model\Issue();
		$issue->load($issue_id);
		$f3->set("issue", $issue);
		// Get recipient list and remove current user
		$recipients = $this->_issue_watchers($issue_id);
		$recipients = array_diff($recipients, array($f3->get("user.email")));

		// Render message body
		$f3->set("issue", $issue);

		$body = \Template::instance()->render("notification/update.html");

		// Set up headers
		//$smtp = $this->smtp_instance();
		//$smtp->set("Subject", "[#" . $issue->id . "] - " . $update->user_name . " created  " . $issue->name);
		//$smtp->set("From", $f3->get("mail.from"));
		//$smtp->set("Reply-to", $f3->get("mail.from"));
		//$smtp->set("Content-type", "text/html");

		// Send to recipients
		$subject =  "[#" . $issue->id . "] - ".$comment->user_name . " created " . $issue->name;
		// Send to recipients
		foreach($recipients as $recipient) {

			// To send HTML mail, the Content-type header must be set
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

			// Additional headers
			$headers .= 'To: '.$recipient . "\r\n";
			$headers .= 'From: '. $f3->get("mail.from") . "\r\n";
			//$smtp->set("To", $recipient);
			//$smtp->send($body);
			mail($recipient, $subject, $body, $headers);
			$log->write("Sent update notification to: " . $recipient);
		}

		$log->write($smtp->log());
	}

	// Get array of email addresses of all watchers on an issue
	protected function _issue_watchers($issue_id) {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");
		$recipients = array();

		// Add issue author and owner
		$result = $db->exec("SELECT u.email FROM issue i INNER JOIN `user` u on i.author_id = u.id WHERE i.id = :id", array("id" => $issue_id));
		$recipients[] = $result[0]["email"];
		$result = $db->exec("SELECT u.email FROM issue i INNER JOIN `user` u on i.owner_id = u.id WHERE i.id = :id", array("id" => $issue_id));
		$recipients[] = $result[0]["email"];

		// Add watchers
		$watchers = $db->exec("SELECT u.email FROM issue_watcher w INNER JOIN `user` u ON w.user_id = u.id WHERE issue_id = :id", array("id" => $issue_id));
		foreach($watchers as $watcher) {
			$recipients[] = $watcher["email"];
		}

		// Remove duplicate users
		return array_unique($recipients);
	}

	protected function smtp_instance() {
		$f3 = \Base::instance();
		$smtp = new \SMTP($f3->get("smtp.host"), $f3->get("smtp.port"), $f3->get("smtp.scheme"), $f3->get("smtp.user"), $f3->get("smtp.pass"));
		return $smtp;
	}

}
