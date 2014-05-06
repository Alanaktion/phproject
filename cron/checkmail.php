<?php
require_once "base.php";

	/* connect to gmail */
	$hostname = $f3->get("imap.hostname");
	$username = $f3->get("imap.username");
	$password = $f3->get("imap.password");

	$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to IMAP: ' . imap_last_error());

	$emails = imap_search($inbox,'ALL UNSEEN');

	if($emails) {
		// put the newest emails on top
		//rsort($emails);

		// for every email...
		$reg_email = "/([_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3}))/i";
		foreach($emails as $email_number) {

			// get information specific to this email

			$header = imap_headerinfo($inbox, $email_number);
			$message = imap_fetchbody($inbox,$email_number,2);

			// is the sender a user?
			$from = $header->from[0]->mailbox . "@" . $header->from[0]->host ;

			$user = new \Model\User();
			$user->load(array('email=? AND (deleted_date IS NULL OR deleted_date = ?)', $from, '0000-00-00 00:00:00'));

			if (!empty($user->id) ) {
				$author = $user->id;

				//Find an owner from the recipients
				foreach($header->to as $owner_email) {
					$user->reset();
					$to = $owner_email->mailbox . "@" . $owner_email->host ;
					$user->load(array('email=?', $to));
					if(!empty($user->id)) {
						$owner = $user->id;
						break;
					} else {
						$owner = $author;
					}
				}

				preg_match("/\[#([0-9]+)\] -/", $header->subject, $matches);

				$issue = new \Model\Issue();
				!empty($matches[1]) ? $issue->load($matches[1]) : '';

				// post a comment if replying to an issue
				if(!empty($issue->id)) {
					$comment = new \Model\Issue\Comment();
					$comment->user_id = $author;
					$comment->issue_id = $issue->id;
					$comment->text = strip_tags($message);
					$comment->created_date = now();
					$comment->save();

					$notification = \Helper\Notification::instance();
					$notification->issue_comment($issue->id, $comment->id);
				} else {

					if(!empty($header->subject)) {
						$subject = trim(preg_replace("/^((Re|Fwd?):\s)*/i", "", $header->subject));
						$issue->load(array('name=? AND (deleted_date IS NULL OR deleted_date = "0000-00-00 00:00:00") AND (closed_date IS NULL OR closed_date = "0000-00-00 00:00:00")', $subject));
					}

					if(!empty($issue->id)) {
						$comment = new \Model\Issue\Comment();
						$comment->user_id = $author;
						$comment->issue_id = $issue->id;
						$comment->text = strip_tags($message);
						$comment->created_date = now();
						$comment->save();

						$notification = \Helper\Notification::instance();
						$notification->issue_comment($issue->id, $comment->id);
					} else {
						$issue->name = $header->subject;
						$issue->description = strip_tags($message);
						$issue->author_id = $author;
						$issue->owner_id = $owner;
						$issue->type_id = 1;
						$issue->save();
					}
				}


				// add other recipients as watchers
				$watchers = array_merge($header->to, $header->cc);
				var_dump($watchers);
				foreach($watchers as $more_people) {
					$watcher_email = $more_people->mailbox . "@" . $more_people->host;
					$watcher = new \Model\User();
					$watcher->load(array('email=? AND (deleted_date IS NULL OR deleted_date != ?)', $watcher_email, '0000-00-00 00:00:00'));

					if(!empty($watcher->id)){
						$watching = new \Model\Issue\Watcher();
						// Loads just in case the user is already a watcher
						$watching->load(array("issue_id = ? AND user_id = ?", $issue->id, $watcher->id));
						$watching->issue_id = $issue->id;
						$watching->user_id =  $watcher->id;
						$watching->save();
					}

				}

			}

		}

	}

	// close the connection
	imap_close($inbox);


?>