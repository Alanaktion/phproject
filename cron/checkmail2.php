<?php

require_once "base.php";
$log = new \Log("checkmail.log");

$imap = $f3->get("imap");
if (!isset($imap["hostname"])) {
    throw new Exception("No IMAP hostname specified in configuration");
}

$inbox = imap_open($imap['hostname'], $imap['username'], $imap['password']);
if ($inbox === false) {
    $err = 'Cannot connect to IMAP: %s';
    $log->write(sprintf($err, imap_last_error()));
    throw new Exception($err);
}

$emails = imap_search($inbox, 'ALL UNSEEN');
if (!$emails) {
    return;
}

foreach ($emails as $msg_number) {
    $header = imap_headerinfo($inbox, $msg_number);
    $structure = imap_fetchstructure($inbox, $msg_number);

    $text = "";
    $attachments = [];

    // Use text from non-multipart messages directly
    if (empty($structure->parts)) {
        // Get message body
        $text = imap_fetchbody($inbox, $msg_number, 1);
        $part = imap_bodystruct($inbox, $msg_number, 1);

        // Decode body
        if ($part->encoding == 4) {
            $text = imap_qprint($text);
        } elseif ($part->encoding == 3) {
            $text = imap_base64($text);
        }
    }

    // Load message parts for multipart messages
    if (!empty($structure->parts)) {
        foreach ($structure->parts as $part_number => $part) {
            // Handle plaintext
            if ($part->type === 0 && !trim($text)) {
                $text = imap_fetchbody($inbox, $msg_number, $part_number + 1);

                // Decode body
                if ($part->encoding == 4) {
                    $text = imap_qprint($text);
                } elseif ($part->encoding == 3) {
                    $text = imap_base64($text);
                }

                // Un-HTML an HTML part
                if ($part->ifsubtype && $part->subtype == 'HTML') {
                    $text = html_entity_decode(strip_tags($text));
                }
            }

            // Handle multipart
            elseif ($part->type == 1 && !trim($text) && !empty($part->parts)) {
                foreach ($part->parts as $multipart_number => $multipart) {
                    $text = imap_fetchbody($inbox, $msg_number, ($part_number + 1) . '.' . $multipart_number);

                    // Decode body
                    if ($multipart->encoding == 4) {
                        $text = imap_qprint($text);
                    } elseif ($multipart->encoding == 3) {
                        $text = imap_base64($text);
                    }

                    // Un-HTML an HTML part
                    if ($multipart->ifsubtype && $multipart->subtype == 'HTML') {
                        $text = html_entity_decode(strip_tags($text));
                    }
                }
            }

            // Handle attachments
            elseif ($part->type > 1 && $part->ifdisposition && $part->disposition == 'ATTACHMENT') {
                // Get filename
                $filename = '';
                if ($part->ifdparameters) {
                    foreach ($part->dparameters as $param) {
                        if ($param->attribute == 'FILENAME') {
                            $filename = $param->value;
                            break;
                        }
                    }
                } elseif ($part->ifparameters) {
                    foreach ($part->parameters as $param) {
                        if ($param->attribute == 'NAME') {
                            $filename = $param->value;
                            break;
                        }
                    }
                }

                // Store attachment metadata
                $attachments[] = [
                    'part_number' => $part_number,
                    'filename' => $filename,
                    'size' => $part->bytes,
                    'encoding' => $part->encoding,
                ];
            }
        }
    }

    if (!$text) {
        continue;
    }

    // Truncate text to prevent long chains from being included
    $truncate = $f3->get("mail.truncate_lines");
    if (!is_array($truncate)) {
        $truncate = $f3->split($truncate);
    }
    foreach ($truncate as $truncator) {
        $parts = explode($truncator, $text);
        $text = $parts[0];
    }

    $from = $header->from[0]->mailbox . "@" . $header->from[0]->host;
    $from_user = new \Model\User();
    $from_user->load(['email = ? AND deleted_date IS NULL', $from]);
    if (!$from_user->id) {
        if (isset($imap['default_user'])) {
            $from_user->load($imap['default_user']);
            $log->write(sprintf('No matching user, using default - From: %s; Subject: %s', $from, $header->subject));
        } else {
            $log->write(sprintf('Skipping message, no matching user - From: %s; Subject: %s', $from, $header->subject));
            continue;
        }
    }

    $to_user = new \Model\User();
    $owner = $from_user->id;
    foreach ($header->to as $to_email) {
        $to = $to_email->mailbox . "@" . $to_email->host;
        $to_user->load(['email = ? AND deleted_date IS NULL', $to]);
        if ($to_user->id) {
            $owner = $to_user->id;
            break;
        }
    }

    // Find issue IDs in subject
    preg_match("/\[#([0-9]+)\] -/", $header->subject, $matches);

    // Get issue instance
    $issue = new \Model\Issue();
    if (!empty($matches[1])) {
        $issue->load(intval($matches[1]));
    }
    if (!$issue->id) {
        $subject = trim(preg_replace("/^((Re|Fwd?):\s)*/i", "", $header->subject));
        $issue->load(['name=? AND deleted_date IS NULL AND closed_date IS NULL', $subject]);
    }

    if ($issue->id) {
        if (trim($text)) {
            $comment = \Model\Issue\Comment::create([
                'user_id' => $from_user->id,
                'issue_id' => $issue->id,
                'text' => $text,
            ]);
            $log->write(sprintf("Added comment %s on issue #%s - %s", $comment->id, $issue->id, $issue->name));
        }
    } else {
        $issue = \Model\Issue::create([
            'name' => $header->subject,
            'description' => $text,
            'author_id' => $from_user->id,
            'owner_id' => $owner,
            'status' => 1,
            'type_id' => 1,
        ]);
        $log->write(sprintf("Created issue #%s - %s", $issue->id, $issue->name));
    }

    // Add other recipients as watchers
    if (!empty($header->cc) || (is_countable($header->to) ? count($header->to) : 0) > 1) {
        if (!empty($header->cc)) {
            $watchers = array_merge($header->to, $header->cc);
        } else {
            $watchers = $header->to;
        }

        foreach ($watchers as $more_people) {
            $watcher_email = $more_people->mailbox . '@' . $more_people->host;
            $watcher = new \Model\User();
            $watcher->load(['email=? AND deleted_date IS NULL', $watcher_email]);

            if ($watcher->id) {
                $watching = new \Model\Issue\Watcher();
                // Loads just in case the user is already a watcher
                $watching->load(["issue_id = ? AND user_id = ?", $issue->id, $watcher->id]);
                $watching->issue_id = $issue->id;
                $watching->user_id = $watcher->id;
                $watching->save();
            }
        }
    }

    foreach ($attachments as $item) {
        // Skip big files
        if ($item['size'] > $f3->get("files.maxsize")) {
            continue;
        }

        // Load file contents
        $data = imap_fetchbody($inbox, $msg_number, $item['part_number'] + 1);

        // Decode contents
        if ($item['encoding'] == 4) {
            $data = imap_qprint($data);
        } elseif ($item['encoding'] == 3) {
            $data = imap_base64($data);
        }

        // Store file
        $dir = 'uploads/' . date('Y/m/');
        $item['filename'] = preg_replace("/[^A-Z0-9._-]/i", "_", $item['filename']);
        $disk_filename = $dir . time() . "_" . $item['filename'];
        if (!is_dir(dirname(__DIR__) . '/' . $dir)) {
            mkdir(dirname(__DIR__) . '/' . $dir, 0777, true);
        }
        file_put_contents(dirname(__DIR__) . '/' . $disk_filename, $data);

        $file = \Model\Issue\File::create([
            "issue_id" => $issue->id,
            "user_id" => $from_user->id,
            "filename" => $item['filename'],
            "disk_directory" => $dir,
            "disk_filename" => $disk_filename,
            "filesize" => strlen($data),
            "content_type" => \Web::instance()->mime($item['filename']),
            "digest" => md5($data),
        ]);

        $log->write(sprintf("Saved file %s on issue #%s - %s", $file->id, $issue->id, $issue->name));
    }
}

imap_close($inbox);
