<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DatabaseSchemaTest extends TestCase
{
    public function testInstallSchemasAllowLongerPasswords(): void
    {
        $mysqlSchema = file_get_contents(dirname(__DIR__) . '/db/database.sql');
        $sqliteSchema = file_get_contents(dirname(__DIR__) . '/db/database.sqlite.sql');

        $this->assertIsString($mysqlSchema);
        $this->assertIsString($sqliteSchema);
        $this->assertStringContainsString("`password` varchar(80) DEFAULT NULL", $mysqlSchema);
        $this->assertStringContainsString("`password` VARCHAR(80) DEFAULT NULL", $sqliteSchema);
        $this->assertStringContainsString("('version', '26.05.13')", $mysqlSchema);
        $this->assertStringContainsString("('version', '26.05.13')", $sqliteSchema);
    }

    public function testMigrationWidensPasswordColumn(): void
    {
        $migration = file_get_contents(dirname(__DIR__) . '/db/26.05.13.sql');

        $this->assertIsString($migration);
        $this->assertStringContainsString('MODIFY COLUMN `password` VARCHAR(80) NULL', $migration);
        $this->assertStringContainsString("UPDATE `config` SET `value` = '26.05.13'", $migration);
    }
}
