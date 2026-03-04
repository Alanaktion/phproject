<?php

namespace Helper;

class Notification extends \Prefab
{
    public const QPRINT_MAXL = 75;

    /**
     * Convert a 8 bit string to a quoted-printable string
     *
     * Modified to add =2E instead of the leading double dot, see GH #238
     *
     * @link http://php.net/manual/en/function.quoted-printable-encode.php#115840
     */
    public function quotePrintEncode(string $str): string
    {
        $lp = 0;
        $ret = '';
        $hex = "0123456789ABCDEF";
        $length = strlen($str);
        $str_index = 0;

        while ($length--) {
            if ((($c = $str[$str_index++]) === "\015") && ($str[$str_index] == "\012") && $length > 0) {
                $ret .= "\015";
                $ret .= $str[$str_index++];
                $length--;
                $lp = 0;
            } elseif (
                ctype_cntrl((string)$c)
                || (ord($c) == 0x7f)
                || (ord($c) & 0x80)
                || ($c === '=')
                || (($c === ' ') && ($str[$str_index] == "\015"))
            ) {
                if (($lp += 3) > self::QPRINT_MAXL) {
                    $ret .= '=';
                    $ret .= "\015";
                    $ret .= "\012";
                    $lp = 3;
                }

                $ret .= '=';
                $ret .= $hex[ord($c) >> 4];
                $ret .= $hex[ord($c) & 0xf];
            } else {
                if ((++$lp) > self::QPRINT_MAXL) {
                    $ret .= '=';
                    $ret .= "\015";
                    $ret .= "\012";
                    $lp = 1;
                }

                $ret .= $c;
                if ($lp == 1 && $c === '.') {
                    $ret = substr($ret, 0, strlen($ret) - 1);
                    $ret .= '=2E';
                    $lp++;
                }
            }
        }

        return $ret;
    }

    /**
     * Send an email with the UTF-8 character set
     * @param string $body The HTML body part
     * @param string|null $text The plaintext body part (optional)
     */
    public function utf8mail(string $to, string $subject, string $body, ?string $text = null): bool
    {
        $f3 = \Base::instance();

        // Add basic headers
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'From: ' . $f3->get("mail.from") . "\r\n";

        // Build multipart message if necessary
        if ($text !== null && $text !== '') {
            // Generate message breaking hash
            $hash = md5(date("r"));
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$hash}\"\r\n";

            // Normalize line endings
            $body = str_replace("\r\n", "\n", $body);
            $body = str_replace("\n", "\r\n", $body);
            $text = str_replace("\r\n", "\n", $text);
            $text = str_replace("\n", "\r\n", $text);

            // Encode content
            $body = $this->quotePrintEncode($body);
            $text = $this->quotePrintEncode($text);

            // Build final message
            $msg = "--{$hash}\r\n";
            $msg .= "Content-Type: text/plain; charset=utf-8\r\n";
            $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
            $msg .= "\r\n" . $text . "\r\n";
            $msg .= "--{$hash}\r\n";
            $msg .= "Content-Type: text/html; charset=utf-8\r\n";
            $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
            $msg .= "\r\n" . $body . "\r\n";
            $msg .= "--{$hash}--\r\n";

            $body = $msg;
        } else {
            $headers .= "Content-Type: text/html; charset=utf-8\r\n";
        }

        return mail($to, $subject, $body, $headers);
    }

    /**
     * Send an email to watchers with the comment body
     */
    public function issue_comment(int $issue_id, int $comment_id): void
    {
        $f3 = \Base::instance();
        if ($f3->get("mail.from")) {
            $log = new \Log("mail.log");

            // Get issue and comment data
            $issue = new \Model\Issue();
            $issue->load($issue_id);
            $comment = new \Model\Issue\Comment\Detail();
            $comment->load($comment_id);

            // Get issue parent if set
            if ($issue->parent_id) {
                $parent = new \Model\Issue();
                $parent->load($issue->parent_id);
                $f3->set("parent", $parent);
            }

            // Get recipient list and remove current user
            $recipients = $this->_issue_watchers($issue_id);
            $recipients = array_filter($recipients, fn($r) => $r['email'] !== $comment->user_email);

            // Set template variables
            $f3->set("issue", $issue);
            $f3->set("comment", $comment);
            $f3->set("previewText", $comment->text);

            $subject = "[#{$issue->id}] - New comment on {$issue->name}";

            // Send to recipients in their preferred language
            foreach ($recipients as $recipient) {
                $lang = $this->_set_language($recipient['language']);
                $text = $this->_render("notification/comment.txt");
                $body = $this->_render("notification/comment.html");
                $f3->set("LANGUAGE", $lang);
                $this->utf8mail($recipient['email'], $subject, $body, $text);
                $log->write("Sent comment notification to: " . $recipient['email']);
            }
        }
    }

    /**
     * Send an email to watchers detailing the updated fields
     */
    public function issue_update(int $issue_id, int $update_id): ?bool
    {
        $f3 = \Base::instance();
        if ($f3->get("mail.from")) {
            $log = new \Log("mail.log");

            // Get issue and update data
            $issue = new \Model\Issue();
            $issue->load($issue_id);
            $f3->set("issue", $issue);
            $update = new \Model\Custom("issue_update_detail");
            $update->load($update_id);

            // Get issue parent if set
            if ($issue->parent_id) {
                $parent = new \Model\Issue();
                $parent->load($issue->parent_id);
                $f3->set("parent", $parent);
            }

            // Avoid errors from bad calls
            if (!$issue->id || !$update->id) {
                return false;
            }

            $changes = new \Model\Issue\Update\Field();
            $f3->set("changes", $changes->find(["issue_update_id = ?", $update->id]));

            // Get recipient list and remove update user
            $recipients = $this->_issue_watchers($issue_id);
            $recipients = array_filter($recipients, fn($r) => $r['email'] !== $update->user_email);

            // Set template variables
            $f3->set("issue", $issue);
            $f3->set("update", $update);

            $changes->load(["issue_update_id = ? AND `field` = 'closed_date' AND old_value = '' and new_value != ''", $update->id]);
            if ($changes && $changes->id) {
                $subject = "[#{$issue->id}] - {$issue->name} closed";
            } else {
                $subject =  "[#{$issue->id}] - {$issue->name} updated";
            }

            // Send to recipients in their preferred language
            foreach ($recipients as $recipient) {
                $lang = $this->_set_language($recipient['language']);
                $text = $this->_render("notification/update.txt");
                $body = $this->_render("notification/update.html");
                $f3->set("LANGUAGE", $lang);
                $this->utf8mail($recipient['email'], $subject, $body, $text);
                $log->write("Sent update notification to: " . $recipient['email']);
            }
        }

        return null;
    }

    /**
     * Send an email to watchers detailing the updated fields
     */
    public function issue_create(int $issue_id): void
    {
        $f3 = \Base::instance();
        $log = new \Log("mail.log");
        if ($f3->get("mail.from")) {
            $log = new \Log("mail.log");

            // Get issue and update data
            $issue = new \Model\Issue\Detail();
            $issue->load($issue_id);
            $f3->set("issue", $issue);

            // Get issue parent if set
            if ($issue->parent_id) {
                $parent = new \Model\Issue();
                $parent->load($issue->parent_id);
                $f3->set("parent", $parent);
            }

            // Get recipient list, conditionally removing the author
            $recipients = $this->_issue_watchers($issue_id);
            $user = new \Model\User();
            $user->load($issue->author_id);
            if ($user->option('disable_self_notifications')) {
                $recipients = array_filter($recipients, fn($r) => $r['email'] !== $user->email);
            }

            // Set template variables
            $f3->set("issue", $issue);

            $subject = "[#{$issue->id}] - {$issue->name} created by {$issue->author_name}";

            // Send to recipients in their preferred language
            foreach ($recipients as $recipient) {
                $lang = $this->_set_language($recipient['language']);
                $text = $this->_render("notification/new.txt");
                $body = $this->_render("notification/new.html");
                $f3->set("LANGUAGE", $lang);
                $this->utf8mail($recipient['email'], $subject, $body, $text);
                $log->write("Sent create notification to: " . $recipient['email']);
            }
        }
    }

    /**
     * Send an email to watchers with the file info
     */
    public function issue_file(int $issue_id, int $file_id): void
    {
        $f3 = \Base::instance();
        if ($f3->get("mail.from")) {
            $log = new \Log("mail.log");

            // Get issue and comment data
            $issue = new \Model\Issue();
            $issue->load($issue_id);
            $file = new \Model\Issue\File\Detail();
            $file->load($file_id);

            // This should catch a bug I can't currently find the source of. --Alan
            if ($file->issue_id != $issue->id) {
                return;
            }

            // Get issue parent if set
            if ($issue->parent_id) {
                $parent = new \Model\Issue();
                $parent->load($issue->parent_id);
                $f3->set("parent", $parent);
            }

            // Get recipient list and remove current user
            $recipients = $this->_issue_watchers($issue_id);
            $recipients = array_filter($recipients, fn($r) => $r['email'] !== $file->user_email);

            // Set template variables
            $f3->set("issue", $issue);
            $f3->set("file", $file);
            $f3->set("previewText", $file->filename);

            $subject =  "[#{$issue->id}] - {$file->user_name} attached a file to {$issue->name}";

            // Send to recipients in their preferred language
            foreach ($recipients as $recipient) {
                $lang = $this->_set_language($recipient['language']);
                $text = $this->_render("notification/file.txt");
                $body = $this->_render("notification/file.html");
                $f3->set("LANGUAGE", $lang);
                $this->utf8mail($recipient['email'], $subject, $body, $text);
                $log->write("Sent file notification to: " . $recipient['email']);
            }
        }
    }

    /**
     * Send a user a password reset email
     */
    public function user_reset(int $user_id, string $token): void
    {
        $f3 = \Base::instance();
        if ($f3->get("mail.from")) {
            $user = new \Model\User();
            $user->load($user_id);

            if (!$user->id) {
                throw new \Exception("User does not exist.");
            }

            // Render message body in the user's preferred language
            $lang = $this->_set_language($user->language);
            $f3->set("token", $token);
            $text = $this->_render("notification/user_reset.txt");
            $body = $this->_render("notification/user_reset.html");
            $f3->set("LANGUAGE", $lang);

            // Send email to user
            $subject = "Reset your password - " . $f3->get("site.name");
            $this->utf8mail($user->email, $subject, $body, $text);
        }
    }

    /**
     * Send a user an email listing the issues due today and any overdue issues
     */
    public function user_due_issues(\Model\User $user, array $due, array $overdue): bool
    {
        $f3 = \Base::instance();
        if ($f3->get("mail.from")) {
            $f3->set("due", $due);
            $f3->set("overdue", $overdue);
            $preview = count($due) . " issues due today";
            if ($overdue !== []) {
                $preview .= ", " . count($overdue) . " overdue issues";
            }

            $f3->set("previewText", $preview);
            $subject = "Due Today - " . $f3->get("site.name");
            $lang = $this->_set_language($user->language);
            $text = $this->_render("notification/user_due_issues.txt");
            $body = $this->_render("notification/user_due_issues.html");
            $f3->set("LANGUAGE", $lang);
            return $this->utf8mail($user->email, $subject, $body, $text);
        }

        return false;
    }

    /**
     * Get array of watchers (email and language) for an issue
     */
    protected function _issue_watchers(int $issue_id): array
    {
        $db = \Base::instance()->get("db.instance");
        $recipients = [];

        // Add issue author and owner
        $result = $db->exec("SELECT u.email, u.language FROM issue i INNER JOIN `user` u on i.author_id = u.id WHERE u.deleted_date IS NULL AND i.id = ?", $issue_id);
        if (!empty($result[0]["email"])) {
            $recipients[] = ['email' => $result[0]["email"], 'language' => $result[0]["language"]];
        }


        $result = $db->exec("SELECT u.email, u.language FROM issue i INNER JOIN `user` u on i.owner_id = u.id WHERE u.deleted_date IS NULL AND i.id = ?", $issue_id);
        if (!empty($result[0]["email"])) {
            $recipients[] = ['email' => $result[0]["email"], 'language' => $result[0]["language"]];
        }

        // Add whole group
        $result = $db->exec("SELECT u.role, u.id FROM issue i INNER JOIN `user` u on i.owner_id = u.id  WHERE u.deleted_date IS NULL AND i.id = ?", $issue_id);
        if ($result && $result[0]["role"] == 'group') {
            $group_users = $db->exec("SELECT u.email, u.language FROM user_group g INNER JOIN `user` u ON g.user_id = u.id WHERE u.deleted_date IS NULL AND g.group_id = ?", $result[0]["id"]);
            foreach ($group_users as $group_user) {
                if (!empty($group_user["email"])) {
                    $recipients[] = ['email' => $group_user["email"], 'language' => $group_user["language"]];
                }
            }
        }

        // Add watchers
        $watchers = $db->exec("SELECT u.email, u.language FROM issue_watcher w INNER JOIN `user` u ON w.user_id = u.id WHERE u.deleted_date IS NULL AND issue_id = ?", $issue_id);
        foreach ($watchers as $watcher) {
            $recipients[] = ['email' => $watcher["email"], 'language' => $watcher["language"]];
        }

        // Remove duplicate users, keeping first occurrence
        $seen = [];
        $unique = [];
        foreach ($recipients as $recipient) {
            $email = $recipient['email'];
            if (!isset($seen[$email])) {
                $seen[$email] = true;
                $unique[] = $recipient;
            }
        }
        return $unique;
    }

    /**
     * Temporarily set the app language, returning the previous value for restoration
     */
    protected function _set_language(?string $language): string
    {
        $f3 = \Base::instance();
        $original = (string) $f3->get("LANGUAGE");

        // If no explicit language is provided, fall back to a configured site default.
        if ($language === null || $language === '') {
            $defaultLanguage = (string) $f3->get("site.default_language");
            if ($defaultLanguage === '') {
                $defaultLanguage = (string) $f3->get("site.language");
            }
            $language = $defaultLanguage !== '' ? $defaultLanguage : $language;
        }

        if ($language) {
            $f3->set("LANGUAGE", $language);
        }
        return $original;
    }

    /**
     * Render a view and return the result
     */
    protected function _render(string $file, string $mime = "text/html", ?array $hive = null, int $ttl = 0): string
    {
        return \Helper\View::instance()->render($file, $mime, $hive, $ttl);
    }
}
