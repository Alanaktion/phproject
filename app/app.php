<?php

final class App {

    static private $fw;

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
            'AUTOLOAD' => 'app/',
            'ESCAPE' => false,
            'PREFIX' => 'dict.',
            'PACKAGE' => 'Phproject',
            'UI' => 'app/view/',
            'LOGS' => 'logs/',
            'JAR.httponly' => false,
        ]);

        // Load configuration
        if(is_file('config.ini')) {
            self::$fw->config('config.ini');
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

        // @todo: initialize plugins
        // @todo: handle authentication

        // Run app
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
     * Trigger router error
     * @param int $code
     */
    static function error($code = null)
    {
        return self::$fw->error($code);
    }

}
