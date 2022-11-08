<?php

namespace Controller;

class User extends \Controller
{
    protected $_userId;

    private $_languages;

    public function __construct()
    {
        $this->_userId = $this->_requireLogin();
        $this->_languages = [
            "en" => \ISO::LC_en,
            "en-GB" => \ISO::LC_en . " (Great Britain)",
            "es" => \ISO::LC_es . " (Español)",
            "fr" => \ISO::LC_fr . " (Français)",
            "pl" => \ISO::LC_pl . " (Polszczyzna)",
            "pt" => \ISO::LC_pt . " (Português)",
            "it" => \ISO::LC_it . " (Italiano)",
            "ru" => \ISO::LC_ru . " (Pу́сский)",
            "nl" => \ISO::LC_nl . " (Nederlands)",
            "de" => \ISO::LC_de . " (Deutsch)",
            "cs" => \ISO::LC_cs . " (Češka)",
            "et" => \ISO::LC_et . " (Eesti)",
            "zh" => \ISO::LC_zh . " (中国)",
            "ja" => \ISO::LC_ja . " (日本語)",
        ];
    }

    /**
     * GET /user/dashboard
     * User dashboard
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function dashboard($f3)
    {
        $dashboard = $f3->get("user_obj")->option("dashboard");
        $helper = \Helper\Dashboard::instance();
        if (!$dashboard || !is_array($dashboard)) {
            $dashboard = $helper->defaultConfig;
        }

        // Load dashboard widget data
        $allWidgets = $helper->allWidgets;
        $missing = [];
        foreach ($dashboard as $k => $widgets) {
            foreach ($widgets as $l => $widget) {
                if (in_array($widget, $allWidgets)) {
                    $f3->set($widget, $helper->$widget());
                } else {
                    $f3->set("error", "Some dashboard widgets cannot be displayed.");
                    $missing[] = [$k, $l];
                }
                unset($allWidgets[array_search($widget, $allWidgets)]);
            }
        }
        foreach ($missing as $kl) {
            unset($dashboard[$kl[0]][$kl[1]]);
        }
        $f3->set("unused_widgets", $allWidgets);

        // Get current sprint if there is one
        $sprint = new \Model\Sprint();
        $localDate = date('Y-m-d', \Helper\View::instance()->utc2local());
        $sprint->load(["? BETWEEN start_date AND end_date", $localDate]);
        $f3->set("sprint", $sprint);

        $f3->set("dashboard", $dashboard);
        $f3->set("menuitem", "index");
        $this->_render("user/dashboard.html");
    }

    /**
     * POST /user/dashboard
     * Save dashboard widget selections
     *
     * @param \Base $f3
     */
    public function dashboardPost($f3)
    {
        $this->validateCsrf();
        $helper = \Helper\Dashboard::instance();
        $widgets = json_decode($f3->get("POST.widgets"), null, 512, JSON_THROW_ON_ERROR);
        $allWidgets = $helper->allWidgets;

        // Validate widget list
        $valid = true;
        foreach ($widgets as $col) {
            foreach ($col as $widget) {
                if (!in_array($widget, $allWidgets)) {
                    $valid = false;
                }
            }
        }
        if (!$valid) {
            $widgets = $helper->defaultConfig;
        }

        $user = $f3->get("user_obj");
        $user->option("dashboard", $widgets);
        $user->save();
        if ($f3->get("AJAX")) {
            $this->_printJson($widgets);
        } else {
            $f3->reroute("/");
        }
    }

    /**
     * Get array of theme names
     * @return array
     */
    private function _loadThemes()
    {
        $themes = ["bootstrap.min"];
        foreach (glob("css/bootstrap-*.css") as $file) {
            $themes[] = pathinfo($file, PATHINFO_FILENAME);
        }
        \Base::instance()->set("themes", $themes);
        return $themes;
    }

    /**
     * GET /user
     *
     * @param \Base $f3
     */
    public function account($f3)
    {
        $f3->set("title", $f3->get("dict.my_account"));
        $f3->set("menuitem", "user");
        $f3->set("languages", $this->_languages);
        $this->_loadThemes();
        $this->_render("user/account.html");
    }

    /**
     * POST /user
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function save($f3)
    {
        $this->validateCsrf();
        $f3 = \Base::instance();
        $post = array_map("trim", $f3->get("POST"));

        $user = new \Model\User();
        $user->load($this->_userId);

        if (!empty($post["old_pass"])) {
            $security = \Helper\Security::instance();

            // Update password
            if (hash_equals($security->hash($post["old_pass"], $user->salt), $user->password)) {
                $min = $f3->get("security.min_pass_len");
                if (strlen($post["new_pass"]) >= $min) {
                    if ($post["new_pass"] == $post["new_pass_confirm"]) {
                        $user->salt = $security->salt();
                        $user->password = $security->hash($post["new_pass"], $user->salt);
                        $f3->set("success", "Password updated successfully.");
                    } else {
                        $f3->set("error", "New passwords do not match");
                    }
                } else {
                    $f3->set("error", "New password must be at least {$min} characters.");
                }
            } else {
                $f3->set("error", "Current password entered is not valid.");
            }
        } elseif (!empty($post["action"]) && $post["action"] == "options") {
            // Update option values
            $user->option("disable_mde", !empty($post["disable_mde"]));
            $user->option("disable_due_alerts", !empty($post["disable_due_alerts"]));
            $user->option("disable_self_notifications", !empty($post["disable_self_notifications"]));
        } else {
            // Update profile
            if (!empty($post["name"])) {
                $user->name = $post["name"];
            } else {
                $error = "Please enter your name.";
            }
            if (preg_match("/^([\p{L}\.\\-\d]+)@([\p{L}\-\.\d]+)((\.(\p{L})+)+)$/im", $post["email"])) {
                $user->email = $post["email"];
            } else {
                $error = $post["email"] . " is not a valid email address.";
            }
            if (empty($error) && ctype_xdigit(ltrim($post["task_color"], "#"))) {
                $user->task_color = ltrim($post["task_color"], "#");
            } elseif (empty($error)) {
                $error = $post["task_color"] . " is not a valid color code.";
            }

            if (empty($post["theme"])) {
                $user->theme = null;
            } else {
                $user->theme = $post["theme"];
            }

            if (empty($post["language"])) {
                $user->language = null;
            } else {
                $user->language = $post["language"];
            }

            if (empty($error)) {
                $f3->set("success", "Profile updated successfully.");
            } else {
                $f3->set("error", $error);
            }
        }

        $user->save();
        $f3->set("title", $f3->get("dict.my_account"));
        $f3->set("menuitem", "user");

        // Use new user values for page
        $user->loadCurrent();

        $f3->set("languages", $this->_languages);
        $this->_loadThemes();

        $this->_render("user/account.html");
    }

    /**
     * POST /user/avatar
     *
     * @param \Base $f3
     * @throws \Exception
     */
    public function avatar($f3)
    {
        $this->validateCsrf();
        $f3 = \Base::instance();

        $user = new \Model\User();
        $user->load($this->_userId);
        if (!$user->id) {
            $f3->error(404);
            return;
        }

        $web = \Web::instance();

        $f3->set("UPLOADS", 'uploads/avatars/');
        if (!is_dir($f3->get("UPLOADS"))) {
            mkdir($f3->get("UPLOADS"), 0777, true);
        }
        $overwrite = true;
        $slug = true;

        // Make a good name
        $parts = pathinfo($_FILES['avatar']['name']);
        $_FILES['avatar']['name'] = $user->id . "-" . substr(uniqid(), 0, 4)  . "." . $parts["extension"];
        $f3->set("avatar_filename", $_FILES['avatar']['name']);

        // Verify file is an image
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $allowedTypes = ['image/jpeg', 'image/gif', 'image/png', 'image/bmp'];
        if (!in_array(finfo_file($finfo, $_FILES['avatar']['tmp_name']), $allowedTypes)) {
            $f3->error(415);
            return;
        }
        finfo_close($finfo);

        $web->receive(
            function ($file) use ($f3, $user) {
                if ($file['size'] > $f3->get("files.maxsize")) {
                    return false;
                }

                $user->avatar_filename = $f3->get("avatar_filename");
                $user->save();
                return true;
            },
            $overwrite,
            $slug
        );

        // Clear cached profile picture data
        $cache = \Cache::instance();
        // @1x
        $cache->clear($f3->hash("GET /avatar/48/{$user->id}.png") . ".url");
        $cache->clear($f3->hash("GET /avatar/96/{$user->id}.png") . ".url");
        $cache->clear($f3->hash("GET /avatar/128/{$user->id}.png") . ".url");
        // @2x
        $cache->clear($f3->hash("GET /avatar/192/{$user->id}.png") . ".url");
        $cache->clear($f3->hash("GET /avatar/256/{$user->id}.png") . ".url");

        $f3->reroute("/user");
    }

    /**
     * GET /user/@username
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single($f3, $params)
    {
        $this->_requireLogin();

        $user = new \Model\User();
        $user->load(["username = ?", $params["username"]]);

        if ($user->id && (!$user->deleted_date || $f3->get("user.rank") >= 3)) {
            $f3->set("title", $user->name);
            $f3->set("this_user", $user);

            // Extra arrays required for bulk update
            $status = new \Model\Issue\Status();
            $f3->set("statuses", $status->find(null, null, $f3->get("cache_expire.db")));

            $f3->set("users", $user->getAll());
            $f3->set("groups", $user->getAllGroups());

            $priority = new \Model\Issue\Priority();
            $f3->set("priorities", $priority->find(null, ["order" => "value DESC"], $f3->get("cache_expire.db")));

            $type = new \Model\Issue\Type();
            $f3->set("types", $type->find(null, null, $f3->get("cache_expire.db")));

            $issue = new \Model\Issue\Detail();
            $f3->set("created_issues", $issue->paginate(
                0,
                200,
                ["status_closed = '0' AND deleted_date IS NULL AND author_id = ?", $user->id],
                ["order" => "priority DESC, due_date DESC"]
            ));
            $f3->set("assigned_issues", $issue->paginate(
                0,
                200,
                ["status_closed = '0' AND deleted_date IS NULL AND owner_id = ?", $user->id],
                ["order" => "priority DESC, due_date DESC"]
            ));
            $f3->set("overdue_issues", $issue->paginate(0, 200, ["status_closed = '0' AND deleted_date IS NULL AND owner_id = ? AND due_date IS NOT NULL AND due_date < ?", $user->id, date("Y-m-d", \Helper\View::instance()->utc2local())], ["order" => "due_date ASC"]));

            $this->_render("user/single.html");
        } else {
            $f3->error(404);
        }
    }

    /**
     * Convert a flat issue array to a tree array. Child issues are added to
     * the 'children' key in each issue.
     * @param  array $array Flat array of issues, including all parents needed
     * @return array Tree array where each issue contains its child issues
     */
    protected function _buildTree($array)
    {
        $tree = [];

        // Create an associative array with each key being the ID of the item
        foreach ($array as $k => &$v) {
            $tree[$v['id']] = &$v;
        }

        // Loop over the array and add each child to their parent
        foreach ($tree as $k => &$v) {
            if (empty($v['parent_id'])) {
                continue;
            }
            $tree[$v['parent_id']]['children'][] = &$v;
        }

        // Loop over the array again and remove any items that don't have a parent of 0;
        foreach ($tree as $k => &$v) {
            if (empty($v['parent_id'])) {
                continue;
            }
            unset($tree[$k]);
        }

        return $tree;
    }

    /**
     * GET /user/@username/tree
     *
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function single_tree($f3, $params)
    {
        $this->_requireLogin();

        $user = new \Model\User();
        $user->load(["username = ? AND deleted_date IS NULL", $params["username"]]);

        if ($user->id) {
            $f3->set("title", $user->name);
            $f3->set("this_user", $user);

            $tree = \Helper\Dashboard::instance()->issue_tree();

            $f3->set("issues", $tree);
            $this->_render($f3->get("AJAX") ? "user/single/tree/ajax.html" : "user/single/tree.html");
        } else {
            $f3->error(404);
        }
    }
}
