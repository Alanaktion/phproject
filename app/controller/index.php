<?php

namespace Controller;

class Index extends \Controller
{
    /**
     * GET /
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function index($f3)
    {
        if ($f3->get("user.id")) {
            $user_controller = new \Controller\User();
            return $user_controller->dashboard($f3);
        } else {
            if ($f3->get("site.public")) {
                $this->_render("index/index.html");
            } else {
                if ($f3->get("site.demo") && is_numeric($f3->get("site.demo"))) {
                    $user = new \Model\User();
                    $user->load($f3->get("site.demo"));
                    if ($user->id) {
                        $session = new \Model\Session($user->id);
                        $session->setCurrent();
                        $f3->set("user", $user->cast());
                        $f3->set("user_obj", $user);
                        $user_controller = new \Controller\User();
                        return $user_controller->dashboard($f3);
                    } else {
                        $f3->set("error", "Auto-login failed, demo user was not found.");
                    }
                }
                $f3->reroute("/login");
            }
        }
    }

    /**
     * GET /login
     *
     * @param \Base $f3
     */
    public function login($f3)
    {
        if ($f3->get("user.id")) {
            if (!$f3->get("GET.to")) {
                $f3->reroute("/");
            } else {
                if (strpos($f3->get("GET.to"), "://") === false || substr($f3->get("GET.to"), 0, 2) == "//") {
                    $f3->reroute($f3->get("GET.to"));
                } else {
                    $f3->reroute("/");
                }
            }
        } else {
            if ($f3->get("GET.to")) {
                $f3->set("to", $f3->get("GET.to"));
            }
            $this->_render("index/login.html");
        }
    }

    /**
     * POST /login
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function loginpost($f3)
    {
        $this->validateCsrf();
        $user = new \Model\User();

        // Load user by username or email address
        if (strpos($f3->get("POST.username"), "@")) {
            $user->load(["email=? AND deleted_date IS NULL", $f3->get("POST.username")]);
        } else {
            $user->load(["username=? AND deleted_date IS NULL", $f3->get("POST.username")]);
        }

        // Verify password
        $security = \Helper\Security::instance();
        if ($user->id && hash_equals($security->hash($f3->get("POST.password"), $user->salt ?: ""), $user->password)) {
            // Create a session and use it
            $session = new \Model\Session($user->id);
            $session->setCurrent();

            if ($user->salt) {
                if (!$f3->get("POST.to")) {
                    $f3->reroute("/");
                } else {
                    if (strpos($f3->get("POST.to"), "://") === false || substr($f3->get("POST.to"), 0, 2) == "//") {
                        $f3->reroute($f3->get("POST.to"));
                    } else {
                        $f3->reroute("/");
                    }
                }
            } else {
                $f3->set("user", $user->cast());
                $this->_render("index/reset_forced.html");
            }
        } else {
            if ($f3->get("POST.to")) {
                $f3->set("to", $f3->get("POST.to"));
            }
            $f3->set("login.error", "Invalid login information, try again.");
            $this->_render("index/login.html");
        }
    }

    /**
     * POST /register
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function registerpost($f3)
    {
        $this->validateCsrf();

        // Exit immediately if public registrations are disabled
        if (!$f3->get("site.public_registration")) {
            $f3->error(400);
            return;
        }

        $errors = [];
        $user = new \Model\User();

        // Check for existing users
        $user->load(["email=?", $f3->get("POST.register-email")]);
        if ($user->id) {
            $user->reset();
            $errors[] = "A user already exists with this email address.";
        }
        $user->load(["username=?", $f3->get("POST.register-username")]);
        if ($user->id) {
            $user->reset();
            $errors[] = "A user already exists with this username.";
        }

        // Validate user data
        if (!$f3->get("POST.register-name")) {
            $errors[] = "Name is required";
        }
        if (!preg_match("/^[0-9a-z]{4,}$/i", $f3->get("POST.register-username"))) {
            $errors[] = "Usernames must be at least 4 characters and can only contain letters and numbers.";
        }
        if (!filter_var($f3->get("POST.register-email"), FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email address is required.";
        }
        if (strlen($f3->get("POST.register-password")) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }

        // Show errors or create new user
        if ($errors) {
            $f3->set("register.error", implode("<br>", $errors));
            $this->_render("index/login.html");
        } else {
            $user->reset();
            $user->username = trim($f3->get("POST.register-username"));
            $user->email = trim($f3->get("POST.register-email"));
            $user->name = trim($f3->get("POST.register-name"));
            $security = \Helper\Security::instance();
            $hash = $security->hash($f3->get("POST.register-password"));
            extract($hash);
            $user->password = $hash;
            $user->salt = $salt;
            $user->task_color = sprintf("%02X%02X%02X", random_int(0, 0xFF), random_int(0, 0xFF), random_int(0, 0xFF));
            $user->rank = \Model\User::RANK_CLIENT;
            $user->save();

            // Create a session and use it
            $session = new \Model\Session($user->id);
            $session->setCurrent();

            $f3->reroute("/");
        }
    }

    /**
     * GET|POST /reset
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function reset($f3)
    {
        if ($f3->get("user.id")) {
            $f3->reroute("/");
        } else {
            if ($f3->get("POST.email")) {
                $this->validateCsrf();
                $user = new \Model\User();
                $user->load(["email = ?", $f3->get("POST.email")]);
                if ($user->id && !$user->deleted_date) {
                    // Re-generate reset token
                    $token = $user->generateResetToken();
                    $user->save();

                    // Send notification
                    $notification = \Helper\Notification::instance();
                    $notification->user_reset($user->id, $token);

                    $f3->set("reset.success", "We've sent an email to " . $f3->get("POST.email") . " with a link to reset your password.");
                } else {
                    $f3->set("reset.error", "No user exists with the email address " . $f3->get("POST.email") . ".");
                }
            }
            unset($user);
            $this->_render("index/reset.html");
        }
    }

    /**
     * GET|POST /reset/@token
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function reset_complete($f3, $params)
    {
        if ($f3->get("user.id")) {
            $f3->reroute("/");
            return;
        }

        if (!$params["token"]) {
            $f3->reroute("/login");
            return;
        }

        $user = new \Model\User();
        $user->load(["reset_token = ?", hash("sha384", $params["token"])]);
        if (!$user->id || !$user->validateResetToken($params["token"])) {
            $f3->set("reset.error", "Invalid reset URL.");
            $this->_render("index/reset.html");
            return;
        }

        if ($f3->get("POST.password1")) {
            $this->validateCsrf();

            // Validate new password
            if ($f3->get("POST.password1") != $f3->get("POST.password2")) {
                $f3->set("reset.error", "The given passwords don't match.");
            } elseif (strlen($f3->get("POST.password1")) < 6) {
                $f3->set("reset.error", "The given password is too short. Passwords must be at least 6 characters.");
            } else {
                // Save new password and redirect to login
                $security = \Helper\Security::instance();
                $user->reset_token = null;
                $user->salt = $security->salt();
                $user->password = $security->hash($f3->get("POST.password1"), $user->salt);
                $user->save();
                $f3->reroute("/login");
                return;
            }
        }
        $f3->set("resetuser", $user);
        $this->_render("index/reset_complete.html");
    }

    /**
     * GET|POST /reset/forced
     *
     * @param \Base $f3
     */
    public function reset_forced($f3)
    {
        $user = new \Model\User();
        $user->loadCurrent();

        if ($f3->get('POST')) {
            $this->validateCsrf();
        }

        if ($f3->get("POST.password1") != $f3->get("POST.password2")) {
            $f3->set("reset.error", "The given passwords don't match.");
        } elseif (strlen($f3->get("POST.password1")) < 6) {
            $f3->set("reset.error", "The given password is too short. Passwords must be at least 6 characters.");
        } else {
            // Save new password and redirect to dashboard
            $security = \Helper\Security::instance();
            $user->salt = $security->salt();
            $user->password = $security->hash($f3->get("POST.password1"), $user->salt);
            $user->save();
            $f3->reroute("/");
            return;
        }
        $this->_render("index/reset_forced.html");
    }

    /**
     * POST /logout
     *
     * @param \Base $f3
     */
    public function logout($f3)
    {
        $this->validateCsrf();
        $session = new \Model\Session();
        $session->loadCurrent();
        $session->delete();
        $f3->reroute("/");
    }

    /**
     * GET /atom.xml
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function atom($f3)
    {
        // Authenticate user
        if ($f3->get("GET.key")) {
            $user = new \Model\User();
            $user->load(["api_key = ?", $f3->get("GET.key")]);
            if (!$user->id) {
                $f3->error(403);
                return;
            }
        } else {
            $f3->error(403);
            return;
        }

        // Get requested array substituting defaults
        $get = $f3->get("GET") + ["type" => "assigned", "user" => $user->username];
        unset($user);

        // Load target user
        $user = new \Model\User();
        $user->load(["username = ?", $get["user"]]);
        if (!$user->id) {
            $f3->error(404);
            return;
        }

        // Load issues
        $issue = new \Model\Issue\Detail();
        $options = ["order" => "created_date DESC"];
        if ($get["type"] == "assigned") {
            $issues = $issue->find(["author_id = ? AND status_closed = 0 AND deleted_date IS NULL", $user->id], $options);
        } elseif ($get["type"] == "created") {
            $issues = $issue->find(["owner_id = ? AND status_closed = 0 AND deleted_date IS NULL", $user->id], $options);
        } elseif ($get["type"] == "all") {
            $issues = $issue->find("status_closed = 0 AND deleted_date IS NULL", $options + ["limit" => 50]);
        } else {
            $f3->error(400, "Invalid feed type");
            return;
        }

        // Render feed
        $f3->set("get", $get);
        $f3->set("feed_user", $user);
        $f3->set("issues", $issues);
        $this->_render("index/atom.xml", "application/atom+xml");
    }

    /**
     * GET /opensearch.xml
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function opensearch($f3)
    {
        $this->_render("index/opensearch.xml", "application/opensearchdescription+xml");
    }
}
