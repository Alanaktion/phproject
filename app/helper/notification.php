<?php

namespace Helper;

class Notification extends \Prefab {

	// Send an email to watchers with the comment body
	public function issue_comment($issue_id, $comment_id) {
		$f3 = \Base::instance();
		$db = $f3->get("db.instance");

		// Get issue and comment data
		$issue = new \Model\Issue();
		$issue->load(array("id = ?", $issue_id));
		$comment = new \DB\SQL\Mapper($f3->get("db.instance"), "issue_comment_user", null, 3600);
		$comment->load(array("id = ?", $comment_id));

		// Build recipient list
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

		// Remove duplicate users and the current user
		$recipients = array_unique($recipients);
		$recipients = array_diff($recipients, array($f3->get("user.email")));

		// Render message body
		$f3->set("issue", $issue->cast());
		$f3->set("comment", $comment->cast());
		$body = \Template::instance()->render("notification/comment.html");

		// Set up headers
		$smtp = $this->smtp_instance();
		$smtp->set("Subject", $comment->user_name . " commented on #" . $issue->id . " " . $issue->name);
		$smtp->set("From", $f3->get("mail.from"));
		$smtp->set("Reply-to", $f3->get("smtp.from"));
		$smtp->set("Content-type", "text/html");

		// Send to recipients
		foreach($recipients as $recipient) {
			$smtp->set("To", $recipient);
			$smtp->send($body);
		}
	}

	// Send an email to watchers detailing the updated fields
	public function issue_update($issue_id, $update_id) {

	}

	protected function smtp_instance() {
		$f3 = \Base::instance();
		$smtp = new \SMTP($f3->get("smtp.host"), $f3->get("smtp.port"), $f3->get("smtp.scheme"), $f3->get("smtp.user"), $f3->get("smtp.pass"));
		return $smtp;
	}

}
