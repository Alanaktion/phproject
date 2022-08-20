<?php

abstract class Controller
{
    /**
     * Require a user to be logged in. Redirects to /login if a session is not found.
     * @param  int $rank
     * @return int|bool
     */
    protected function _requireLogin($rank = \Model\User::RANK_CLIENT)
    {
        $f3 = \Base::instance();
        if ($id = $f3->get("user.id")) {
            if ($f3->get("user.rank") >= $rank) {
                return $id;
            } else {
                $f3->error(403);
                return false;
            }
        } else {
            if ($f3->get("site.demo") && is_numeric($f3->get("site.demo"))) {
                $user = new \Model\User();
                $user->load($f3->get("site.demo"));
                if ($user->id) {
                    $session = new \Model\Session($user->id);
                    $session->setCurrent();
                    $f3->set("user", $user->cast());
                    $f3->set("user_obj", $user);
                    return;
                } else {
                    $f3->set("error", "Auto-login failed, demo user was not found.");
                }
            }
            if (empty($_GET)) {
                $f3->reroute("/login?to=" . urlencode($f3->get("PATH")));
            } else {
                $f3->reroute("/login?to=" . urlencode($f3->get("PATH")) . urlencode("?" . http_build_query($_GET)));
            }
            return false;
        }
    }

    /**
     * Require a user to be an administrator. Throws HTTP 403 if logged in, but not an admin.
     * @param  int $rank
     * @return int|bool
     */
    protected function _requireAdmin($rank = \Model\User::RANK_ADMIN)
    {
        $id = $this->_requireLogin();

        $f3 = \Base::instance();
        if ($f3->get("user.role") == "admin" && $f3->get("user.rank") >= $rank) {
            return $id;
        } else {
            $f3->error(403);
            return false;
        }
    }

    /**
     * Render a view
     * @param string  $file
     * @param string  $mime
     * @param array   $hive
     * @param integer $ttl
     */
    protected function _render($file, $mime = "text/html", array $hive = null, $ttl = 0)
    {
        echo \Helper\View::instance()->render($file, $mime, $hive, $ttl);
    }

    /**
     * Output object as JSON and set appropriate headers
     * @param mixed $object
     */
    protected function _printJson($object)
    {
        if (!headers_sent()) {
            header("Content-type: application/json");
        }
        echo json_encode($object, JSON_THROW_ON_ERROR);
    }

    /**
     * Get current time and date in a MySQL NOW() format
     * @param  boolean $time  Whether to include the time in the string
     * @return string
     */
    public function now($time = true)
    {
        return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
    }

    /**
     * Validate the request's CSRF token, exiting if invalid
     */
    protected function validateCsrf()
    {
        \Helper\Security::instance()->validateCsrfToken();
    }
}
