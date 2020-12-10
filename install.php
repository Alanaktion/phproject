<?php

// Initialize core
require_once "vendor/autoload.php";
$f3 = Base::instance();
$f3->mset(array(
    "UI" => "app/view/",
    "LOGS" => "log/",
    "TEMP" => "tmp/",
    "PREFIX" => "dict.",
    "LOCALES" => "app/dict/",
    "FALLBACK" => "en",
    "CACHE" => false,
    "AUTOLOAD" => "app/",
    "PACKAGE" => "Phproject",
    "site.url" => $f3->get("SCHEME") . "://" . $f3->get("HOST") . $f3->get("BASE") . "/"
));

// Check if already installed
if (is_file("config.php")) {
    $f3->set("success", "Phproject is already installed.");
}

// Check PCRE version
if (version_compare((float)PCRE_VERSION, '7.9', '<')) {
    $f3->set("error", "PCRE version is out of date");
}

// Check for MySQL PDO
if (!in_array("mysql", PDO::getAvailableDrivers())) {
    $f3->set("error", "MySQL PDO driver is not available.");
}

// Check for GD library
if (!function_exists("imagecreatetruecolor")) {
    $f3->set("warning", "GD library is not available. Profile pictures and file thumbnails will not work until it is installed.");
}

// Run installation process if post data received
if (
    ($_POST && !$f3->exists('success') && !$f3->exists('error'))
    || (php_sapi_name() == 'cli')
) {
    if (php_sapi_name() == 'cli') {
        if ($f3->get('error')) {
            echo $f3->get('error'), PHP_EOL;
            exit(3);
        }

        $longOpts = [
            'site-url:' => null,
            'site-name:' => null,
            'timezone::' => 'Etc/UTC',
            'from-email::' => null,
            'lang::' => 'en',
            'parser::' => 'markdown',
            'disable-registration' => false,
            'db-host::' => 'localhost',
            'db-port::' => 3306,
            'db-user::' => 'root',
            'db-password::' => '',
            'db-name::' => 'phproject',
            'admin-username:' => null,
            'admin-email:' => null,
            'admin-password:' => null,
        ];
        $helper = \Helper\Cli::instance();
        $data = $helper->parseOptions($longOpts, $argv);
        if ($data === null) {
            return;
        }

        // Map data to expected $post values
        $post = $data;
        $post['db-pass'] = $data['db-password'];
        $post['site-timezone'] = $data['timezone'];
        $post['mail-from'] = $data['from-email'];
        $post['site-public_registration'] = !$data['disable-registration'];
        $post['user-username'] = $data['admin-username'];
        $post['user-email'] = $data['admin-email'];
        $post['user-password'] = $data['admin-password'];
    } else {
        $post = $_POST;
    }

    try {
        // Connect to database
        $db = new \DB\SQL(
            "mysql:host=" . $post["db-host"] . ";port=" . $post["db-port"] . ";dbname=" . $post["db-name"],
            $post["db-user"],
            $post["db-pass"]
        );

        // Run installation scripts
        $install_db = file_get_contents("db/database.sql");
        foreach (explode(";", $install_db) as $stmt) {
            $db->exec($stmt);
        }

        // Create admin user
        $f3->set("db.instance", $db);
        $security = \Helper\Security::instance();
        $user = new \Model\User();
        $user->role = "admin";
        $user->rank = \Model\User::RANK_SUPER;
        $user->name = "Admin";
        $user->username = $post["user-username"] ?: "admin";
        $user->email = $post["user-email"];
        $user->salt = $security->salt();
        $user->password = $security->hash($post["user-password"] ?: "admin", $user->salt);
        $user->api_key = $security->salt_sha1();
        $user->save();

        // Ensure required directories exist
        if (!is_dir("tmp/cache")) {
            mkdir("tmp/cache", 0777, true);
        }
        if (!is_dir("log")) {
            mkdir("log", 0777, true);
        }

        // Save base configuration
        \Model\Config::setVal('session_lifetime', 604800);
        \Model\Config::setVal('cache_expire.db', 3600);
        \Model\Config::setVal('cache_expire.minify', 86400);
        \Model\Config::setVal('cache_expire.attachments', 2592000);
        \Model\Config::setVal('parse.ids', true);
        \Model\Config::setVal('parse.hashtags', true);
        \Model\Config::setVal('parse.urls', true);
        \Model\Config::setVal('parse.emoticons', true);
        \Model\Config::setVal('site.description', 'A high performance full-featured project management system');
        \Model\Config::setVal('site.demo', 0);
        \Model\Config::setVal('site.theme', 'css/bootstrap-phproject.css');
        \Model\Config::setVal('site.public_registration', 0);
        \Model\Config::setVal('security.block_ccs', 0);
        \Model\Config::setVal('security.min_pass_len', 6);
        \Model\Config::setVal('security.restrict_access', 0);
        \Model\Config::setVal('issue_type.task', 1);
        \Model\Config::setVal('issue_type.project', 2);
        \Model\Config::setVal('issue_type.bug', 3);
        \Model\Config::setVal('issue_priority.default', 0);
        \Model\Config::setVal('gravatar.rating', 'pg');
        \Model\Config::setVal('gravatar.default', 'mm');
        \Model\Config::setVal('mail.truncate_lines', '<--->,--- ---,------------------------------');
        \Model\Config::setVal('files.maxsize', 2097152);

        // Save configuration options
        if (!empty($post["language"])) {
            \Model\Config::setVal("LANGUAGE", $post["language"]);
        }
        if ($post["parser"] == "both") {
            \Model\Config::setVal("parse.markdown", 1);
            \Model\Config::setVal("parse.textile", 1);
        } elseif ($post["parser"] == "markdown") {
            \Model\Config::setVal("parse.markdown", 0);
            \Model\Config::setVal("parse.textile", 1);
        } elseif ($post["parser"] == "textile") {
            \Model\Config::setVal("parse.markdown", 1);
            \Model\Config::setVal("parse.textile", 0);
        }
        \Model\Config::setVal("site.name", $post['site-name']);
        \Model\Config::setVal("site.timezone", $post['site-timezone']);
        \Model\Config::setVal("site.public_registration", $post['site-public_registration']);
        \Model\Config::setVal("mail.from", $post['mail-from']);

        // Write database connection file
        $config = [
            'db.host' => $post['db-host'],
            'db.port' => $post['db-port'] ?: 3306,
            'db.user' => $post['db-user'],
            'db.pass' => $post['db-pass'],
            'db.name' => $post['db-name'],
            'site.url' => $post['site-url'] ?? ($f3->get('SCHEME') . '://' . $f3->get('HOST') . $f3->get('BASE') . '/'),
        ];
        $data = "<?php\nreturn " . var_export($config, true) . ";\n";
        file_put_contents("config.php", $data);

        $f3->set("success", "Installation complete.");
    } catch (PDOException $e) {
        if (php_sapi_name() == 'cli') {
            echo $e->getMessage(), PHP_EOL;
            exit(2);
        } else {
            $f3->set("warning", $e->getMessage());
        }
    }
}

// Render installer template
if (php_sapi_name() != 'cli') {
    echo Template::instance()->render("install.html");
}
