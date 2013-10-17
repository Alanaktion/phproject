<?php

// Initialize core
$f3=require('lib/base.php');
$f3->mset(array(
	"UI" => "app/view/",
	"LOGS" => "log/",
	"LOCALES" => "app/dict/",
	"AUTOLOAD" => "app/"
));

// Load local configuration
$f3->config('config.ini');

// Set up error handling
$f3->set('ONERROR', function($f3) {
	switch($f3->get('ERROR.code')) {
		case 404:
			$f3->set('title', 'Not Found');
			echo Template::instance()->render('error/404.html');
			break;
		case 500:
			$f3->set('title', 'Server Error');
			echo Template::instance()->render('error/500.html');
			break;
		default:
			$f3->set('title', 'Error');
			echo Template::instance()->render('error/general.html');
	}
});

// Connect to database
$f3->set("db.instance", new DB\SQL(
	"mysql:host=" . $f3->get('db.host') . ";port=3306;dbname=" . $f3->get('db.name'),
	$f3->get('db.user'),
	$f3->get('db.pass')
));

// Define routes
$f3->route('GET /ref', function() {
	echo View::instance()->render('userref.html');
});

$f3->route('GET /',function($f3) {
	if(is_file('.maintenance')) {
		echo Template::instance()->render('maintenance.html');
	} else {
		echo Template::instance()->render('index.html');
	}
});

$f3->route('GET /login', function($f3) {
	echo Template::instance()->render('login.html');
});

$f3->route('POST /login', function($f3) {
	$user = new Model\User();
	$user->load(array('username=?',$f3->get('POST.username')));

	if($user->verify_password($f3->get('POST.password'))) {
		echo "Cool, this works.";
		print_r($user);
	} else {
		$f3->set('login.error', 'Invalid login information, try again.');
		echo Template::instance()->render('login.html');
	}
});

// Run the application
$f3->run();
