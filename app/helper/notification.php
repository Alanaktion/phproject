<?php

namespace Helper;

class Notification extends \Prefab {

	/**
	 * Send an email to watchers with the comment body
	 * @param  int $issue_id
	 * @param  int $comment_id
	 */
	public function issue_comment($issue_id, $comment_id) {
		$f3 = \Base::instance();
		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

			// Get issue and comment data
			$issue = new \Model\Issue;
			$issue->load($issue_id);
			$comment = new \Model\Issue\Comment\Detail;
			$comment->load($comment_id);

			// Get issue parent if set
			if($issue->parent_id) {
				$parent = new \Model\Issue;
				$parent->load($issue->parent_id);
				$f3->set("parent", $parent);
			}

			// Get recipient list and remove current user
			$recipients = $this->_issue_watchers($issue_id);
			$recipients = array_diff($recipients, array($comment->user_email));

			// Render message body
			$f3->set("issue", $issue);
			$f3->set("comment", $comment);
			$body = \Template::instance()->render("notification/comment.html");

			$subject = "[#{$issue->id}] - New comment on {$issue->name}";

			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent comment notification to: " . $recipient);
			}
		}
	}

	/**
	 * Send an email to watchers detailing the updated fields
	 * @param  int $issue_id
	 * @param  int $update_id
	 */
	public function issue_update($issue_id, $update_id) {
		$f3 = \Base::instance();
		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

			// Get issue and update data
			$issue = new \Model\Issue();
			$issue->load($issue_id);
			$f3->set("issue", $issue);
			$update = new \Model\Custom("issue_update_detail");
			$update->load($update_id);

			// Get issue parent if set
			if($issue->parent_id) {
				$parent = new \Model\Issue;
				$parent->load($issue->parent_id);
				$f3->set("parent", $parent);
			}

			// Avoid errors from bad calls
			if(!$issue->id || !$update->id) {
				return false;
			}

			$changes = new \Model\Issue\Update\Field();
			$f3->set("changes", $changes->find(array("issue_update_id = ?", $update->id)));

			// Get recipient list and remove update user
			$recipients = $this->_issue_watchers($issue_id);
			$recipients = array_diff($recipients, array($update->user_email));

			// Render message body
			$f3->set("issue", $issue);
			$f3->set("update", $update);
			$body = \Template::instance()->render("notification/update.html");

			$closed = $changes->load(array("issue_update_id = ? AND `field` = 'closed_date' AND old_value = '' and new_value != ''", $update->id));
			if($closed->id) {
				$subject = "[#{$issue->id}] - {$issue->name} closed";
			} else {
				$subject =  "[#{$issue->id}] - {$issue->name} updated";
			}



			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent update notification to: " . $recipient);
			}
		}
	}

	/**
	 * Send an email to watchers detailing the updated fields
	 * @param  int $issue_id
	 */
	public function issue_create($issue_id) {
		$f3 = \Base::instance();
		$log = new \Log("mail.log");
		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

			// Get issue and update data
			$issue = new \Model\Issue\Detail();
			$issue->load($issue_id);
			$f3->set("issue", $issue);

			// Get issue parent if set
			if($issue->parent_id) {
				$parent = new \Model\Issue;
				$parent->load($issue->parent_id);
				$f3->set("parent", $parent);
			}

			// Get recipient list, keeping current user
			$recipients = $this->_issue_watchers($issue_id);

			// Render message body
			$f3->set("issue", $issue);

			$body = \Template::instance()->render("notification/new.html");

			$subject =  "[#{$issue->id}] - {$issue->name} created by {$issue->author_name}";

			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent create notification to: " . $recipient);
			}
		}
	}

	/**
	 * Send an email to watchers with the file info
	 * @param  int $issue_id
	 * @param  int $file_id
	 */
	public function issue_file($issue_id, $file_id) {
		$f3 = \Base::instance();
		if($f3->get("mail.from")) {
			$log = new \Log("mail.log");

			// Get issue and comment data
			$issue = new \Model\Issue;
			$issue->load($issue_id);
			$file = new \Model\Issue\File\Detail;
			$file->load($file_id);

			// Get issue parent if set
			if($issue->parent_id) {
				$parent = new \Model\Issue;
				$parent->load($issue->parent_id);
				$f3->set("parent", $parent);
			}

			// Get recipient list and remove current user
			$recipients = $this->_issue_watchers($issue_id);
			$recipients = array_diff($recipients, array($file->user_email));

			// Render message body
			$f3->set("issue", $issue);
			$f3->set("file", $file);
			$body = \Template::instance()->render("notification/file.html");

			$subject =  "[#{$issue->id}] - {$file->user_name} attached a file to {$issue->name}";

			// Send to recipients
			foreach($recipients as $recipient) {
				utf8mail($recipient, $subject, $body);
				$log->write("Sent file notification to: " . $recipient);
			}
		}
	}

	/**
	 * Send a user a password reset email
	 * @param  int $user_id
	 */
	public function user_reset($user_id) {
		$f3 = \Base::instance();
		if($f3->get("mail.from")) {
			$user = new \Model\User;
			$user->load($user_id);

			if(!$user->id) {
				throw new Exception("User does not exist.");
			}

			// Render message body
			$f3->set("user", $user);
			$body = \Template::instance()->render("notification/user_reset.html");

			// Send email to user
			$subject = "Reset your password - " . $f3->get("site.name");
			utf8mail($user->email, $subject, $body);
		}
	}

	/**
	 * Send a user an email listing the issues due today
	 * @param  ModelUser $user
	 * @param  array     $issues
	 * @return bool
	 */
	public function user_due_issues(\Model\User $user, array $issues) {
		$f3 = \Base::instance();
		if($f3->get("mail.from")) {
			$f3->set("issues", $issues);
			$subject = "Due Today - " . $f3->get("site.name");
			$body = \Template::instance()->render("notification/user_due_issues.html");
			return utf8mail($user->email, $subject, $body);
		}
		return false;
	}

	/**
	 * Get array of email addresses of all watchers on an issue
	 * @param  int $issue_id
	 * @return array
	 */
	protected function _issue_watchers($issue_id) {
		$f3 = \Base::instance();
		$log = new \Log("mail.log");
		$db = $f3->get("db.instance");
		$recipients = array();

		// Add issue author and owner
		$result = $db->exec("SELECT u.email FROM issue i INNER JOIN `user` u on i.author_id = u.id WHERE i.id = :id", array("id" => $issue_id));
		if(!empty( $result[0]["email"])) {
			$recipients[] = $result[0]["email"];
		}


		$result = $db->exec("SELECT u.email FROM issue i INNER JOIN `user` u on i.owner_id = u.id WHERE i.id = :id", array("id" => $issue_id));
		if(!empty( $result[0]["email"])) {
			$recipients[] = $result[0]["email"];
		}

		// Add whole group
		$result = $db->exec("SELECT u.role, u.id FROM issue i INNER JOIN `user` u on i.owner_id = u.id  WHERE i.id = :id", array("id" => $issue_id));
		if($result && $result[0]["role"] == 'group') {
			$group_users = $db->exec("SELECT g.user_email FROM user_group_user g  WHERE g.group_id = :id", array("id" => $result[0]["id"]));
			foreach($group_users as $group_user) {
				if(!empty( $group_user["user_email"])) {
					$recipients[] = $group_user["user_email"];
				}
			}
		}

		// Add watchers
		$watchers = $db->exec("SELECT u.email FROM issue_watcher w INNER JOIN `user` u ON w.user_id = u.id WHERE issue_id = :id", array("id" => $issue_id));
		foreach($watchers as $watcher) {
			$recipients[] = $watcher["email"];
		}

		// Remove duplicate users
		return array_unique($recipients);
	}

}
