<?php
/**
 * Cron job base initialization
 * @author   Alan Hardman <alan@phpizza.com>
 */

if (PHP_SAPI != 'cli') {
    throw new Exception("Cron jobs must be run from the command line.");
}

$homedir = dirname(dirname(__FILE__)) . "/";
set_include_path($homedir);

// Init app
require_once 'app/app.php';
App::init();
App::db();

// Load database-backed config
\Model\Config::loadAll();
