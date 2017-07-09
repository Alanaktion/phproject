<?php

final class App {

    static private $fw;
    static private $db;

    /**
     * Initialize the app
     * @return boolean
     */
    static function init()
    {
        // Initialize Composer autoloader
        require_once 'vendor/autoload.php';

        // Initialize framework
        self::$fw = Base::instance();
        self::$fw->mset([
            'AUTOLOAD' => 'app/;lib/vendor/',
            'ESCAPE' => false,
            'PREFIX' => 'dict.',
            "LOCALES" => "app/dict/",
            "FALLBACK" => "en",
            'PACKAGE' => 'Phproject',
            'UI' => 'app/view/;app/plugin/',
            'LOGS' => 'log/',
            "TZ" => "UTC",
            'JAR.httponly' => false,
        ]);

        // Load configuration
        if(is_file('config.php')) {
            $config = require_once('config.php');
            self::$fw->mset($config);
            return true;
        } else {
            header('Location: install.php');
            return false;
        }
    }

    /**
     * Start router and handle requests
     */
    static function run()
    {
        // Initialize routes
        require_once 'routes.php';

        // Connect to database
        self::db();

        // Load final configuration
        \Model\Config::loadAll();

        // @todo: initialize plugins

        // @todo: handle authentication
        // Set up user session
        $user = new Model\User();
        $user->loadCurrent();

        // Load issue types
        $types = new \Model\Issue\Type();
        $fw->set("issue_types", $types->find(null, null, self::$fw->get("cache_expire.db")));

        // Run app
        $fw->set("menuitem", false);
        self::$fw->run();
    }

    /**
     * Get a router instance
     * @return Base
     */
    static function fw()
    {
        return self::$fw;
    }

    /**
     * Get database connection, connecting if needed
     * @return \DB\SQL
     */
    static function db()
    {
        if (!self::$db) {
            self::$db = new DB\SQL(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    self::$fw->get("db.host"),
                    self::$fw->get("db.port"),
                    self::$fw->get("db.name")
                ),
                self::$fw->get("db.user"),
                self::$fw->get("db.pass")
            );
        }
        return self::$db;
    }

    /**
     * Trigger router error
     * @param int $code
     */
    static function error($code = null)
    {
        return self::$fw->error($code);
    }

}
