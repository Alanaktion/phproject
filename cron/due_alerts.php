<?php
/**
 *  due_alerts.php
 *  Sends alerts to users with issues due today
 */

require_once "base.php";

$user = new \Model\User;
$users = $user->find();

foreach($users as $u) {
	$u->sendDueAlert();
}
