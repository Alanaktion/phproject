<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));
require_once("vendor/autoload.php");
$f3 = \Base::instance();
$f3->mset(array(
    "UI" => "app/view/;app/plugin/",
    "ESCAPE" => false,
    "LOGS" => "log/",
    "TEMP" => "tmp/",
    "PREFIX" => "dict.",
    "LOCALES" => "app/dict/",
    "FALLBACK" => "en",
    "CACHE" => false,
    "AUTOLOAD" => "app/;lib/vendor/",
    "PACKAGE" => "Phproject",
    "TZ" => "UTC",
    "site.timezone" => "America/Phoenix"
));

if (getenv('TRAVIS')) {
    // Connect to MySQL on Travis CI
    $f3->mset([
        'db.host' => '127.0.0.1',
        'db.port' => '3306',
        'db.user' => 'root',
        'db.pass' => '',
        'db.name' => 'phproject',
        'TRAVIS' => true,
    ]);
    $f3->set('db.instance', new DB\SQL(
        'mysql:host=' . $f3->get('db.host') . ';port=' . $f3->get('db.port') . ';dbname=' . $f3->get('db.name'),
        $f3->get('db.user'),
        $f3->get('db.pass')
    ));

    // Import database
    $db = $f3->get('db.instance');
    $install_db = file_get_contents(__DIR__ . '/../db/database.sql');
    $db->exec(explode(';', $install_db));

    // Create admin user
    $security = \Helper\Security::instance();
    $user = new \Model\User;
    $user->role = 'admin';
    $user->rank = \Model\User::RANK_SUPER;
    $user->name = 'Admin';
    $user->username = 'admin';
    $user->email = 'admin@localhost';
    $user->salt = $security->salt();
    $user->password = $security->hash('admin', $user->salt);
    $user->api_key = $security->salt_sha1();
    $user->save();
} elseif(is_file(__DIR__ . '/../config.php')) {
    // Load local configuration, if it exists
    $config = require_once(__DIR__ . '/../config.php');
    $f3->mset($config);
    $f3->set('db.instance', new DB\SQL(
        'mysql:host=' . $f3->get('db.host') . ';port=' . $f3->get('db.port') . ';dbname=' . $f3->get('db.name'),
        $f3->get('db.user'),
        $f3->get('db.pass')
    ));
} else {
    $warning = 'No configuration is available, database tests will be skipped.';
    if (DIRECTORY_SEPARATOR == '/') {
        echo "\x1B[33m$warning\x1B[0m\n";
    } else {
        echo "$warning\n";
    }
}
