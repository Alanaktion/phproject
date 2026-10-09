<?php

// Static-analysis shim for the pdo_mysql extension's driver-specific PDO subclass.
//
// Pdo\Mysql only exists on PHP 8.4+ with pdo_mysql loaded, but the codebase
// references Pdo\Mysql::ATTR_INIT_COMMAND behind a PHP_VERSION_ID guard to
// stay compatible with PHP 8.1-8.3 (where PDO::MYSQL_ATTR_INIT_COMMAND is
// not deprecated). PHPStan scans this file (see scanFiles in phpstan.neon)
// so it can resolve the class when analyzing on older PHP versions or
// without the pdo_mysql extension installed.

namespace Pdo;

class Mysql extends \PDO
{
    public const ATTR_INIT_COMMAND = 1002;
}
