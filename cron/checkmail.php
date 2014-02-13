<?php
require_once "base.php";

	/* connect to gmail */
	$hostname = $f3->get("imap.hostname");
	$username = $f3->get("imap.username");
	$password = $f3->get("imap.password");

	$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to IMAP: ' . imap_last_error());

	$emails = imap_search($inbox,'ALL UNSEEN');
	
	if($emails) {
		/* put the newest emails on top */
		rsort($emails);

		/* for every email... */
		
		foreach($emails as $email_number) {

			/* get information specific to this email */
			$overview = imap_fetch_overview($inbox,$email_number,0);
			$message = imap_fetchbody($inbox,$email_number,2);
			
			preg_match("@<(.+)>@", $overview[0]->from, $matches);
			$from = $matches[1];
			
					
			/* is the sender a user? */
			$user = new \Model\User();
			$user->load(array('email=? AND (deleted_date IS NULL OR deleted_date != ?)',$from, '0000-00-00 00:00:00'));
			$user->load(array('email=? ',$from));
			//DEBUG
			print $overview[0]->from. "\n";
			if (!empty($user->id) ) {
				$author = $user->id;
				$user->reset();
				$user->load(array('email=?',$overview[0]->to));
				$owner = !empty($user->id) ? $user->id : '';
				
				preg_match("@^\[#([0-9]+)\] -@", $overview[0]->subject, $matches);
			
				$issue = new \Model\Issue();
				$issue->load($matches[1]);
				
			//DEBUG
			print $matches[1]. "\n";
				/* post a comment if replying to an issue */
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
					$issue->name = $overview[0]->subject;
					$issue->description = strip_tags($message);
					$issue->author_id = $author;
					$issue->owner_id = $owner;
					$issue->type_id = 1;
					$issue->save();
				}
				
			}

		}


	}
	
	/* close the connection */
	imap_close($inbox);

	
?>


