<?php

/*
    checkmail2.php is now preferred over this script! You should use it instead if possible.
*/

require_once "base.php";
$log = new \Log("checkmail.log");

// connect to gmail
$hostname = $f3->get("imap.hostname");
$username = $f3->get("imap.username");
$password = $f3->get("imap.password");

$inbox = imap_open($hostname, $username, $password);
if ($inbox === false) {
    $err = 'Cannot connect to IMAP: ' . imap_last_error();
    $log->write($err);
    throw new Exception($err);
}

$emails = imap_search($inbox, 'ALL UNSEEN');

if ($emails) {
    // for every email...
    $reg_email = "/([_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3}))/i";
    foreach ($emails as $email_number) {
        // get the to and from and strip stuff from the body
        $header = imap_headerinfo($inbox, $email_number);
        $text = imap_fetchbody($inbox, $email_number, 2, FT_INTERNAL);

        // Ensure we get a message body on weird senders
        if (!trim($text)) {
            $text = imap_fetchbody($inbox, $email_number, 1, FT_INTERNAL);
        }

        // Convert quoted-printable to 8-bit
        $message = imap_qprint($text);

        // Clean up line breaks
        $message = str_replace(["<br>", "<br />"], "\r\n", $message);


        $truncate = $f3->get("mail.truncate_lines");
        if (is_string($truncate)) {
            $truncate = $f3->split($truncate);
        }
        foreach ($truncate as $truncator) {
            $parts = explode($truncator, $message);
            $message = $parts[0];
        }

        // is the sender a user?
        $from = $header->from[0]->mailbox . "@" . $header->from[0]->host;

        $user = new \Model\User();
        $user->load(['email=? AND (deleted_date IS NULL OR deleted_date = ?)', $from, '0000-00-00 00:00:00']);

        if (!empty($user->id)) {
            $author = $user->id;

            // find an owner from the recipients
            foreach ($header->to as $owner_email) {
                $user->reset();
                $to = $owner_email->mailbox . "@" . $owner_email->host ;
                $user->load(['email=?', $to]);
                if (!empty($user->id)) {
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
            if (!empty($issue->id)) {
                if (!empty($message)) {
                    $comment = new \Model\Issue\Comment();
                    $comment->user_id = $author;
                    $comment->issue_id = $issue->id;
                    $comment->text = html_entity_decode(strip_tags($message));
                    $comment->created_date = date("Y-m-d H:i:s");
                    $comment->save();

                    $notification = \Helper\Notification::instance();
                    $notification->issue_comment($issue->id, $comment->id);
                }
            } else {
                if (!empty($header->subject)) {
                    $subject = trim(preg_replace("/^((Re|Fwd?):\s)*/i", "", $header->subject));
                    $issue->load(['name=? AND (deleted_date IS NULL OR deleted_date = "0000-00-00 00:00:00") AND (closed_date IS NULL OR closed_date = "0000-00-00 00:00:00")', $subject]);
                }

                if (!empty($issue->id)) {
                    $comment = new \Model\Issue\Comment();
                    $comment->user_id = $author;
                    $comment->issue_id = $issue->id;
                    $comment->text = html_entity_decode(strip_tags($message));
                    $comment->created_date = date("Y-m-d H:i:s");
                    $comment->save();

                    $notification = \Helper\Notification::instance();
                    $notification->issue_comment($issue->id, $comment->id);
                } else {
                    $issue->name = $header->subject;
                    $issue->description = html_entity_decode(strip_tags($message));
                    $issue->author_id = $author;
                    $issue->owner_id = $owner;
                    $issue->type_id = 1;
                    $issue->save();
                    $log->write('Saved issue ' . $issue->id);
                }
            }

            if (!empty($issue->id)) {
                // add other recipients as watchers
                if (!empty($header->cc) || (is_countable($header->to) ? count($header->to) : 0) > 1) {
                    if (!empty($header->cc)) {
                        $watchers = array_merge($header->to, $header->cc);
                    } else {
                        $watchers = $header->to;
                    }

                    foreach ($watchers as $more_people) {
                        $watcher_email = $more_people->mailbox . "@" . $more_people->host;
                        $watcher = new \Model\User();
                        $watcher->load(['email=? AND (deleted_date IS NULL OR deleted_date != ?)', $watcher_email, '0000-00-00 00:00:00']);

                        if (!empty($watcher->id)) {
                            $watching = new \Model\Issue\Watcher();
                            // Loads just in case the user is already a watcher
                            $watching->load(["issue_id = ? AND user_id = ?", $issue->id, $watcher->id]);
                            $watching->issue_id = $issue->id;
                            $watching->user_id =  $watcher->id;
                            $watching->save();
                        }
                    }
                }

                // Copy Attachments as Files
                /* Mod from http://www.codediesel.com/php/downloading-gmail-attachments-using-php/ */
                /* get mail structure */
                $structure = imap_fetchstructure($inbox, $email_number);

                $attachments = [];

                /* if any attachments found... */
                if (isset($structure->parts) && (is_countable($structure->parts) ? count($structure->parts) : 0)) {
                    $count = count($structure->parts);
                    for ($i = 0; $i < $count; $i++) {
                        $attachments[$i] = [
                            'is_attachment' => false,
                            'filename' => '',
                            'name' => '',
                            'attachment' => '',
                            'size' => '',
                        ];

                        if ($structure->parts[$i]->ifdparameters) {
                            foreach ($structure->parts[$i]->dparameters as $object) {
                                if (strtolower($object->attribute) == 'filename') {
                                    $attachments[$i]['is_attachment'] = true;
                                    $attachments[$i]['filename'] = $object->value;
                                }
                            }
                        }

                        if ($structure->parts[$i]->ifparameters) {
                            foreach ($structure->parts[$i]->parameters as $object) {
                                if (strtolower($object->attribute) == 'name') {
                                    $attachments[$i]['is_attachment'] = true;
                                    $attachments[$i]['name'] = $object->value;
                                }
                            }
                        }

                        if ($attachments[$i]['is_attachment']) {
                            $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i + 1);

                            /* 4 = QUOTED-PRINTABLE encoding */
                            if ($structure->parts[$i]->encoding == 3) {
                                $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);

                                /* 3 = BASE64 encoding */
                            } elseif ($structure->parts[$i]->encoding == 4) {
                                $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                            }
                        }
                    }
                }

                // iterate through each attachment and save it
                foreach ($attachments as $attachment) {
                    if ($attachment['is_attachment'] == 1) {
                        $filename = $attachment['name'];
                        if (empty($filename)) {
                            $filename = $attachment['filename'];
                        }

                        if (empty($filename)) {
                            $filename = time() . ".dat";
                        }

                        /*
                         * prefix the email number to the filename in case two emails
                         * have the attachment with the same file name.
                         */

                        // don't forget to set an Upload directory, and make it writable!
                        $f3->set("UPLOADS", 'uploads/' . date("Y") . "/" . date("m") . "/");
                        if (!is_dir(dirname(__DIR__) . '/' . $f3->get("UPLOADS"))) {
                            mkdir(dirname(__DIR__) . '/' . $f3->get("UPLOADS"), 0777, true);
                        }

                        // Make a good name
                        $orig_name = preg_replace("/[^A-Z0-9._-]/i", "_", $filename);
                        $filename = time() . "_" . $orig_name;

                        $i = 0;
                        $parts = pathinfo($filename);
                        while (file_exists(dirname(__DIR__) . '/' . $f3->get("UPLOADS") . $filename)) {
                            $i++;
                            $filename = $parts["filename"] . "-" . $i . "." . $parts["extension"];
                        }


                        $newfile = new \Model\Issue\File();
                        $newfile->issue_id = $issue->id;
                        $newfile->user_id = $user->id;
                        $newfile->filename = $orig_name;
                        $newfile->disk_filename = $f3->get("UPLOADS") . $filename;
                        $newfile->disk_directory = $f3->get("UPLOADS");
                        $newfile->filesize = $file['size'];
                        $newfile->content_type = $file['type'];
                        $newfile->digest = md5($attachment['attachment']);
                        $newfile->created_date = date("Y-m-d H:i:s");
                        $newfile->save();

                        $fp = fopen(dirname(__DIR__) . '/' . $f3->get("UPLOADS")  . $filename, "w+");
                        fwrite($fp, $attachment['attachment']);
                        fclose($fp);
                    }
                }
            }
        }
    }
}

// close the connection
imap_close($inbox);
