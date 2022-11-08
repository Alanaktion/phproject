<?php

/**
 *  due_alerts.php
 *  Sends alerts to users with issues due today
 */

require_once "base.php";

$user = new \Model\User();
$users = $user->getAll();

foreach ($users as $u) {
    if ($u->option('disable_due_alerts')) {
        continue;
    }
    $u->sendDueAlert();
}
