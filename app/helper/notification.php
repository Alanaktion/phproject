<?php

namespace Helper;

class Notification extends \Prefab {

	// Send an email to watchers with the comment body
	public function issue_comment($issue_id, $comment_id) {
		$f3 = \Base::instance();

		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

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

			$subject =  "[#" . $issue->id . "] - ".$comment->user_name . " commented on  " . $issue->name;
			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent comment notification to: " . $recipient);
			}
		}
	}

	// Send an email to watchers detailing the updated fields
	public function issue_update($issue_id, $update_id) {
		$f3 = \Base::instance();

		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

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

			// Get recipient list and remove update user
			$recipients = $this->_issue_watchers($issue_id);
			$recipients = array_diff($recipients, array($f3->get("update.user_email")));

			// Render message body
			$f3->set("issue", $issue);
			$f3->set("update", $update);
			$body = \Template::instance()->render("notification/update.html");

			$subject =  "[#" . $issue->id . "] - ".$update->user_name . " updated  " . $issue->name;
			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent update notification to: " . $recipient);
			}
		}
	}

	// Send an email to watchers detailing the updated fields
	public function issue_create($issue_id) {
		$f3 = \Base::instance();
		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

			// Get issue and update data
			$issue = new \Model\Issue\Detail();
			$issue->load($issue_id);
			$f3->set("issue", $issue);
			// Get recipient list and remove current user
			$recipients = $this->_issue_watchers($issue_id);
			$recipients = array_diff($recipients, array($f3->get("user.email")));

			// Render message body
			$f3->set("issue", $issue);

			$body = \Template::instance()->render("notification/new.html");

			// Send to recipients
			$subject =  "[#" . $issue->id . "] - ".$issue->author_name . " created " . $issue->name;
			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent create notification to: " . $recipient);
			}
		}
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

}
