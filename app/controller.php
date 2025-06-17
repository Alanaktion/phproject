<?php

abstract class Controller
{
    /**
     * Require a user to be logged in. Redirects to /login if a session is not found.
     */
    protected function _requireLogin(int $rank = \Model\User::RANK_CLIENT): int|bool
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
                    return false;
                } else {
                    $f3->set("error", "Auto-login failed, demo user was not found.");
                }
            }
            if ($_GET === []) {
                $f3->reroute("/login?to=" . urlencode((string) $f3->get("PATH")));
            } else {
                $f3->reroute("/login?to=" . urlencode((string) $f3->get("PATH")) . urlencode("?" . http_build_query($_GET)));
            }
            return false;
        }
    }

    /**
     * Require a user to be an administrator. Throws HTTP 403 if logged in, but not an admin.
     */
    protected function _requireAdmin(int $rank = \Model\User::RANK_ADMIN): int|bool
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
     */
    protected function _render(string $file, string $mime = "text/html", ?array $hive = null, int $ttl = 0): void
    {
        echo \Helper\View::instance()->render($file, $mime, $hive, $ttl);
    }

    /**
     * Output object as JSON and set appropriate headers
     * @param mixed $object
     */
    protected function _printJson($object): void
    {
        if (!headers_sent()) {
            header("Content-type: application/json");
        }
        echo json_encode($object, JSON_THROW_ON_ERROR);
    }

    /**
     * Get current time and date in a MySQL NOW() format
     * @param bool $time  Whether to include the time in the string
     */
    public function now(bool $time = true): string
    {
        return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
    }

    /**
     * Validate the request's CSRF token, exiting if invalid
     */
    protected function validateCsrf(): void
    {
        \Helper\Security::instance()->validateCsrfToken();
    }
}
