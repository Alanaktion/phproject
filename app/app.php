<?php

final class App {

    static private $fw;
    static private $db;

    /**
     * Initialize the app
     * @return boolean
     */
    public static function init()
    {
        // Initialize Composer autoloader
        require_once 'vendor/autoload.php';

        // Initialize framework
        self::$fw = Base::instance();
        self::$fw->mset([
            'AUTOLOAD' => 'app/;lib/vendor/',
            'CACHE' => true,
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
    public static function run()
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
    public static function fw()
    {
        if (!self::$fw) {
            self::$fw = \Base::instance();
        }
        return self::$fw;
    }

    /**
     * Get database connection, connecting if needed
     * @return \DB\SQL
     */
    public static function db()
    {
        if (!self::$db) {
            $fw = self::fw();
            self::$db = new DB\SQL(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    $fw->get("db.host"),
                    $fw->get("db.port"),
                    $fw->get("db.name")
                ),
                $fw->get("db.user"),
                $fw->get("db.pass")
            );
        }
        return self::$db;
    }

    /**
     * Trigger router error
     * @param int $code
     */
    public static function error($code = null)
    {
        return self::$fw->error($code);
    }

}
