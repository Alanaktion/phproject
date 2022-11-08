<?php

namespace Controller;

class Admin extends \Controller
{
    protected $_userId;

    public function __construct()
    {
        $this->_userId = $this->_requireAdmin();
        \Base::instance()->set("menuitem", "admin");
    }

    /**
     * GET /admin
     * @param \Base $f3
     */
    public function index(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.administration"));
        $f3->set("menuitem", "admin");

        if ($f3->get("POST.action") == "clearcache") {
            $this->validateCsrf();

            $cache = \Cache::instance();

            // Clear configured cache
            $cache->reset();

            // Clear filesystem cache (thumbnails, etc.)
            $cache->load("folder=tmp/cache/");
            $cache->reset();

            // Reset cache configuration
            $cache->load($f3->get("CACHE"));

            $f3->set("success", "Cache cleared successfully.");
        }

        $db = $f3->get("db.instance");

        // Gather some stats
        $result = $db->exec("SELECT COUNT(id) AS `count` FROM user WHERE deleted_date IS NULL AND role != 'group'");
        $f3->set("count_user", $result[0]["count"]);
        $result = $db->exec("SELECT COUNT(id) AS `count` FROM issue WHERE deleted_date IS NULL");
        $f3->set("count_issue", $result[0]["count"]);
        $result = $db->exec("SELECT COUNT(id) AS `count` FROM issue_comment");
        $f3->set("count_issue_comment", $result[0]["count"]);
        $result = $db->exec("SELECT value as version FROM config WHERE attribute = 'version'");
        $f3->set("version", $result[0]["version"]);

        if ($f3->get("CACHE") == "apc" && function_exists("apc_cache_info")) {
            $f3->set("apc_stats", apc_cache_info("user", true));
        }

        $this->_render("admin/index.html");
    }

    /**
     * GET /admin/release.json
     *
     * Check for a new release and report some basic stats
     *
     * @param \Base $f3
     * @return void
     */
    public function releaseCheck(\Base $f3)
    {
        if (!$f3->get('site.key')) {
            \Model\Config::setVal('site.key', sha1(random_bytes(512)));
        }

        // Gather basic stats
        $data = [
            'release' => PHPROJECT_VERSION,
            'key' => $f3->get('site.key'),
        ];

        if (!$f3->get('site.disable_stats')) {
            $data['revision'] = $f3->get('revision');
            $db = $f3->get('db.instance');
            $result = $db->exec("SELECT COUNT(id) AS `count` FROM user WHERE role != 'group'");
            $data['users'] = $result[0]['count'];
            $result = $db->exec("SELECT COUNT(id) AS `count` FROM user WHERE role = 'group'");
            $data['groups'] = $result[0]['count'];
            $result = $db->exec("SELECT AVG(num) num FROM (SELECT COUNT(*) num FROM user_group GROUP BY group_id) g");
            $data['avg_group_size'] = $result[0]['num'];
            $result = $db->exec("SELECT COUNT(id) AS `count` FROM issue");
            $data['issues'] = $result[0]['count'];
            $result = $db->exec("SELECT COUNT(id) AS `count` FROM sprint");
            $data['sprints'] = $result[0]['count'];
            $result = $db->exec("SELECT value as version FROM config WHERE attribute = 'version'");
            $data['version'] = $result[0]['version'];
        }

        // Make HTTP request
        $endpoint = 'https://meta.phproject.org/release.php';
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        try {
            $result = file_get_contents($endpoint, false, $context);
        } catch (\Exception $e) {
            $this->_printJson(['error' => 1]);
            return;
        }

        $return = json_decode($result);
        if (json_last_error() != JSON_ERROR_NONE) {
            $this->_printJson(['error' => 2]);
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600 * 12) . ' GMT');
        }
        if (!empty($return->description)) {
            // Render markdown description as HTML
            $parsedown = new \Parsedown();
            $parsedown->setUrlsLinked(false);
            $parsedown->setMarkupEscaped(true);
            $return->description_html = $parsedown->text($return->description);
        }
        echo json_encode($return, JSON_THROW_ON_ERROR);
    }

    /**
     * GET /admin/config
     * @param \Base $f3
     */
    public function config(\Base $f3)
    {
        $this->_requireAdmin(\Model\User::RANK_SUPER);

        $status = new \Model\Issue\Status();
        $f3->set("issue_statuses", $status->find());

        $f3->set("title", $f3->get("dict.configuration"));
        $this->_render("admin/config.html");
    }

    /**
     * POST /admin/config/saveattribute
     * @param \Base $f3
     * @throws \Exception
     */
    public function config_post_saveattribute(\Base $f3)
    {
        $this->validateCsrf();
        $this->_requireAdmin(\Model\User::RANK_SUPER);

        $attribute = str_replace("-", ".", $f3->get("POST.attribute"));
        $value = $f3->get("POST.value");
        $response = ["error" => null];

        if (!$attribute) {
            $response["error"] = "No attribute specified.";
            $this->_printJson($response);
            return;
        }

        $config = new \Model\Config();
        $config->load(["attribute = ?", $attribute]);

        $config->attribute = $attribute;
        switch ($attribute) {
            case "site-name":
                if (trim($value)) {
                    $config->value = $value;
                    $config->save();
                } else {
                    $response["error"] = "Site name cannot be empty.";
                }
                break;
            case "site-timezone":
                if (in_array($value, timezone_identifiers_list())) {
                    $config->value = $value;
                    $config->save();
                } else {
                    $response["error"] = "Timezone is invalid.";
                }
                break;
            default:
                $config->value = $value;
                $config->save();
        }

        if (!$response["error"]) {
            $response["attribute"] = $attribute;
            $response["value"] = $value;
        }

        $this->_printJson($response);
    }

    /**
     * GET /admin/plugins
     * @param \Base $f3
     */
    public function plugins(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.plugins"));
        $this->_render("admin/plugins.html");
    }

    /**
     * GET /admin/plugins/@id
     * @param \Base $f3
     * @param array $params
     */
    public function plugin_single(\Base $f3, array $params)
    {
        $this->_requireAdmin(\Model\User::RANK_SUPER);

        $plugins = $f3->get("plugins");
        if ($plugin = $plugins[$params["id"]]) {
            $f3->set("plugin", $plugin);
            if ($f3->get("AJAX")) {
                $plugin->_admin();
            } else {
                $f3->set("title", $plugin->_package());
                $this->_render("admin/plugins/single.html");
            }
        } else {
            $f3->error(404);
        }
    }

    /**
     * GET /users
     * @param \Base $f3
     */
    public function users(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.users"));

        $users = new \Model\User();
        $f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'"));
        $f3->set("select_users", $users->find("deleted_date IS NULL AND role != 'group'", ["order" => "name ASC"]));

        $this->_render("admin/users.html");
    }

    /**
     * GET /admin/users/deleted
     * @param \Base $f3
     */
    public function deleted_users(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.users"));

        $users = new \Model\User();
        $f3->set("users", $users->find("deleted_date IS NOT NULL AND role != 'group'"));

        $this->_render("admin/users/deleted.html");
    }

    /**
     * GET /admin/users/@id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function user_edit(\Base $f3, array $params)
    {
        $f3->set("title", $f3->get("dict.edit_user"));

        $user = new \Model\User();
        $user->load($params["id"]);

        if ($user->id) {
            if ($user->rank > $f3->get("user.rank")) {
                $f3->error(403, "You are not authorized to edit this user.");
                return;
            }
            $f3->set("this_user", $user);
            $this->_render("admin/users/edit.html");
        } else {
            $f3->error(404, "User does not exist.");
        }
    }

    /**
     * GET /admin/users/new
     * @param \Base $f3
     */
    public function user_new(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.new_user"));
        $f3->set("rand_color", sprintf("#%02X%02X%02X", random_int(0, 0xFF), random_int(0, 0xFF), random_int(0, 0xFF)));
        $this->_render("admin/users/edit.html");
    }

    /**
     * POST /admin/users, POST /admin/users/@id
     * @param \Base $f3
     * @throws \Exception
     */
    public function user_save(\Base $f3)
    {
        $this->validateCsrf();
        $security = \Helper\Security::instance();
        $user = new \Model\User();
        $user_id = $f3->get("POST.user_id");

        try {
            // Check for existing users with same info
            $user->load(["username = ? AND id != ?", $f3->get("POST.username"), $user_id]);
            if ($user->id) {
                throw new \Exception("Another user already exists with this username");
            }
            $user->load(["email = ? AND id != ?", $f3->get("POST.email"), $user_id]);
            if ($user->id) {
                throw new \Exception("Another user already exists with this email address");
            }

            if ($user_id) {
                $f3->set("title", $f3->get("dict.edit_user"));
                $user->load($user_id);
                $f3->set("this_user", $user);
            } else {
                $f3->set("title", $f3->get("dict.new_user"));

                // Verify a password is being set
                if (!$f3->get("POST.password")) {
                    throw new \Exception("A password is required for a new user");
                }

                // Set new user fields
                $user->api_key = $security->salt_sha1();
                $user->created_date = $this->now();
            }

            // Validate password if being set
            if ($f3->get("POST.password")) {
                if ($f3->get("POST.password") != $f3->get("POST.password_confirm")) {
                    throw new \Exception("Passwords do not match");
                }
                $min = $f3->get("security.min_pass_len");
                if (strlen($f3->get("POST.password")) < $min) {
                    throw new \Exception("Passwords must be at least {$min} characters");
                }

                // Check if giving user temporary or permanent password
                if ($f3->get("POST.temporary_password")) {
                    $user->salt = null;
                    $user->password = $security->hash($f3->get("POST.password"), "");
                } else {
                    $user->salt = $security->salt();
                    $user->password = $security->hash($f3->get("POST.password"), $user->salt);
                }
            }

            if (!$f3->get("POST.name")) {
                throw new \Exception("Please enter a name.");
            }
            if (!preg_match("/#?[0-9a-f]{3,6}/i", $f3->get("POST.task_color"))) {
                throw new \Exception("Please enter a valid hex color.");
            }
            if (!preg_match("/[0-9a-z_-]+/i", $f3->get("POST.username"))) {
                throw new \Exception("Usernames can only contain letters, numbers, hyphens, and underscores.");
            }
            if (!filter_var($f3->get("POST.email"), FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Please enter a valid email address");
            }

            // Set basic fields
            $user->username = $f3->get("POST.username");
            $user->email = $f3->get("POST.email");
            $user->name = $f3->get("POST.name");
            // Don't allow user to change own rank
            if ($user->id != $f3->get("user.id")) {
                $user->rank = $f3->get("POST.rank");
            }
            $user->role = $user->rank < \Model\User::RANK_ADMIN ? 'user' : 'admin';
            $user->task_color = ltrim($f3->get("POST.task_color"), "#");

            // Save user
            $user->save();
        } catch (\Exception $e) {
            $f3->set("error", $e->getMessage());
            $this->_render("admin/users/edit.html");
            return;
        }

        $f3->reroute("/admin/users#" . $user->id);
    }

    /**
     * POST /admin/users/delete/@id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function user_delete(\Base $f3, array $params)
    {
        $this->validateCsrf();
        $user = new \Model\User();
        $user->load($params["id"]);
        if (!$user->id) {
            $f3->reroute("/admin/users");
            return;
        }

        // Reassign issues if requested
        if ($f3->get("POST.reassign")) {
            switch ($f3->get("POST.reassign")) {
                case "unassign":
                    $user->reassignIssues(null);
                    break;
                case "to-user":
                    $user->reassignIssues($f3->get("POST.reassign-to"));
                    break;
            }
        }

        $user->delete();
        if ($f3->get("AJAX")) {
            $this->_printJson(["deleted" => 1]);
        } else {
            $f3->reroute("/admin/users");
        }
    }

    /**
     * POST /admin/users/undelete/@id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function user_undelete(\Base $f3, array $params)
    {
        $this->validateCsrf();
        $user = new \Model\User();
        $user->load($params["id"]);
        if (!$user->id) {
            $f3->reroute("/admin/users");
            return;
        }

        $user->deleted_date = null;
        $user->save();

        if ($f3->get("AJAX")) {
            $this->_printJson(["deleted" => 1]);
        } else {
            $f3->reroute("/admin/users");
        }
    }

    /**
     * GET /admin/groups
     * @param \Base $f3
     */
    public function groups(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.groups"));

        $group = new \Model\User();
        $groups = $group->find("deleted_date IS NULL AND role = 'group'");

        $group_array = [];
        $db = $f3->get("db.instance");
        foreach ($groups as $g) {
            $db->exec("SELECT g.id FROM user_group g JOIN user u ON g.user_id = u.id WHERE g.group_id = ? AND u.deleted_date IS NULL", $g["id"]);
            $count = $db->count();
            $group_array[] = [
                "id" => $g["id"],
                "name" => $g["name"],
                "task_color" => $g["task_color"],
                "count" => $count,
            ];
        }
        $f3->set("groups", $group_array);

        $this->_render("admin/groups.html");
    }

    /**
     * POST /admin/groups/new
     * @param \Base $f3
     */
    public function group_new(\Base $f3)
    {
        $this->validateCsrf();
        $group = new \Model\User();
        $group->name = $f3->get("POST.name");
        $group->username = \Web::instance()->slug($group->name);
        $group->role = "group";
        $group->task_color = sprintf("%02X%02X%02X", random_int(0, 0xFF), random_int(0, 0xFF), random_int(0, 0xFF));
        $group->created_date = $this->now();
        $group->save();
        $f3->reroute("/admin/groups");
    }

    /**
     * GET /admin/groups/@id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function group_edit(\Base $f3, array $params)
    {
        $f3->set("title", $f3->get("dict.groups"));

        $group = new \Model\User();
        $group->load(["id = ? AND deleted_date IS NULL AND role = 'group'", $params["id"]]);
        $f3->set("group", $group);

        $members = new \Model\Custom("user_group_user");
        $f3->set("members", $members->find(["group_id = ? AND deleted_date IS NULL", $group->id]));

        $users = new \Model\User();
        $f3->set("users", $users->find("deleted_date IS NULL AND role != 'group'", ["order" => "name ASC"]));

        $this->_render("admin/groups/edit.html");
    }

    /**
     * POST /admin/groups/delete/@id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function group_delete(\Base $f3, array $params)
    {
        $this->validateCsrf();
        $group = new \Model\User();
        $group->load($params["id"]);
        $group->delete();
        if ($f3->get("AJAX")) {
            $this->_printJson(["deleted" => 1] + $group->cast());
        } else {
            $f3->reroute("/admin/groups");
        }
    }

    /**
     * POST /admin/groups/ajax
     * @param \Base $f3
     * @throws \Exception
     */
    public function group_ajax(\Base $f3)
    {
        $this->validateCsrf();
        if (!$f3->get("AJAX")) {
            $f3->error(400);
        }

        $group = new \Model\User();
        $group->load(["id = ? AND deleted_date IS NULL AND role = 'group'", $f3->get("POST.group_id")]);

        if (!$group->id) {
            $f3->error(404);
            return;
        }

        switch ($f3->get('POST.action')) {
            case "add_member":
                foreach ($f3->get("POST.user") as $user_id) {
                    $user_group = new \Model\User\Group();
                    $user_group->load(["user_id = ? AND group_id = ?", $user_id, $f3->get("POST.group_id")]);
                    if (!$user_group->id) {
                        $user_group->group_id = $f3->get("POST.group_id");
                        $user_group->user_id = $user_id;
                        $user_group->save();
                    } else {
                        // user already in group
                    }
                }
                break;
            case "remove_member":
                $user_group = new \Model\User\Group();
                $user_group->load(["user_id = ? AND group_id = ?", $f3->get("POST.user_id"), $f3->get("POST.group_id")]);
                $user_group->delete();
                $this->_printJson(["deleted" => 1]);
                break;
            case "change_title":
                $group->name = trim($f3->get("POST.name"));
                $group->username = \Web::instance()->slug($group->name);
                $group->save();
                $this->_printJson(["changed" => 1]);
                break;
            case "change_task_color":
                $group->task_color = ltrim($f3->get("POST.value"), '#');
                $group->save();
                $this->_printJson(["changed" => 1]);
                break;
            case "change_api_visibility":
                $group->api_visible = (int)!!$f3->get("POST.value");
                $group->save();
                $this->_printJson(["changed" => 1]);
                break;
        }
    }

    /**
     * POST /admin/groups/@id/setmanager/@user_group_id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function group_setmanager(\Base $f3, array $params)
    {
        $this->validateCsrf();
        $db = $f3->get("db.instance");

        $group = new \Model\User();
        $group->load(["id = ? AND deleted_date IS NULL AND role = 'group'", $params["id"]]);

        if (!$group->id) {
            $f3->error(404);
            return;
        }

        // Remove Manager status from all members and set manager status on specified user
        $db->exec("UPDATE user_group SET manager = 0 WHERE group_id = ?", $group->id);
        $db->exec("UPDATE user_group SET manager = 1 WHERE id = ?", $params["user_group_id"]);

        $f3->reroute("/admin/groups/" . $group->id);
    }

    /**
     * GET /admin/sprints
     * @param \Base $f3
     */
    public function sprints(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.sprints"));

        $sprints = new \Model\Sprint();
        $f3->set("sprints", $sprints->find());

        $this->_render("admin/sprints.html");
    }

    /**
     * GET|POST /admin/sprints/new
     * @param \Base $f3
     */
    public function sprint_new(\Base $f3)
    {
        $f3->set("title", $f3->get("dict.sprints"));

        if ($post = $f3->get("POST")) {
            $this->validateCsrf();

            if (empty($post["start_date"]) || empty($post["end_date"])) {
                $f3->set("error", "Start and end date are required");
                $this->_render("admin/sprints/new.html");
                return;
            }

            $start = strtotime($post["start_date"]);
            $end = strtotime($post["end_date"]);

            if (!$start || !$end) {
                $f3->set("error", "Please enter a valid start and end date");
                $this->_render("admin/sprints/new.html");
                return;
            }

            if ($end <= $start) {
                $f3->set("error", "End date must be after start date");
                $this->_render("admin/sprints/new.html");
                return;
            }

            $sprint = new \Model\Sprint();
            $sprint->name = trim($post["name"]);
            $sprint->start_date = date("Y-m-d", $start);
            $sprint->end_date = date("Y-m-d", $end);
            $sprint->save();
            $f3->reroute("/admin/sprints");
            return;
        }

        $this->_render("admin/sprints/new.html");
    }

    /**
     * GET /admin/sprints/@id, POST /admin/sprints/@id
     * @param \Base $f3
     * @param array $params
     * @throws \Exception
     */
    public function sprint_edit(\Base $f3, array $params)
    {
        $f3->set("title", $f3->get("dict.sprints"));

        $sprint = new \Model\Sprint();
        $sprint->load($params["id"]);
        if (!$sprint->id) {
            $f3->error(404);
            return;
        }

        if ($post = $f3->get("POST")) {
            if (empty($post["start_date"]) || empty($post["end_date"])) {
                $f3->set("error", "Start and end date are required");
                $this->_render("admin/sprints/edit.html");
                return;
            }

            $start = strtotime($post["start_date"]);
            $end = strtotime($post["end_date"]);

            if ($end <= $start) {
                $f3->set("error", "End date must be after start date");
                $this->_render("admin/sprints/edit.html");
                return;
            }

            $sprint->name = trim($post["name"]);
            $sprint->start_date = date("Y-m-d", $start);
            $sprint->end_date = date("Y-m-d", $end);
            $sprint->save();

            $f3->reroute("/admin/sprints");
            return;
        }
        $f3->set("sprint", $sprint);

        $this->_render("admin/sprints/edit.html");
    }
}
