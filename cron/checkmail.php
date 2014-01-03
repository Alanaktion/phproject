<?php
require_once "base.php";

// IN PROGRESS!!!
// NEED TO RUN THIS ONLY AS A CRON

if( TRUE ){ // $f3->get("cronjob") ) {
	/* connect to gmail */
	$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
	$username = 'shelf@alanaktion.net';
	$password = 'shelfy123';

	$inbox = imap_open($hostname,$username,$password) or die('Cannot connect to Gmail: ' . imap_last_error());

	$emails = imap_search($inbox,'ALL UNSEEN');

	if($emails) {
		echo "found mail";

		/* put the newest emails on top */
		rsort($emails);

		/* for every email... */
		foreach($emails as $email_number) {

			/* get information specific to this email */
			$overview = imap_fetch_overview($inbox,$email_number,0);
			$message = imap_fetchbody($inbox,$email_number,2);

			$issue = new Model\Issue();

			$issue->name = $overview[0]->subject;
			$issue->description = $message;
			$issue->save();


		}


	}

	echo "all done again";

	/* close the connection */
	imap_close($inbox);
} else {
	$f3->error(403, "Internal Authentication Required");
}


