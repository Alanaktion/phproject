<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/MigrationFakeMySql.php";

class MigrationTest extends TestCase
{
    private string $migrationFile;

    protected function setUp(): void
    {
        $this->migrationFile = dirname(__DIR__) . "/db/99.99.99.sql";
    }

    protected function tearDown(): void
    {
        if (file_exists($this->migrationFile)) {
            unlink($this->migrationFile);
        }
        $f3 = \Base::instance();
        $f3->clear("db.instance");
        $f3->clear("error");
        $f3->clear("success");
    }

    private function writeMigration(string $sql): void
    {
        file_put_contents($this->migrationFile, $sql);
    }

    private function isAlreadyApplied(string $driver, string $message): bool
    {
        $method = new \ReflectionMethod(\Helper\Security::class, "isAlreadyAppliedSchemaError");
        $method->setAccessible(true);
        return $method->invoke(\Helper\Security::instance(), $driver, new \Exception($message));
    }

    public function testClassifierToleratesOnlyReplayedSchemaObjects(): void
    {
        // Replayed DDL on MySQL: safe to skip.
        $this->assertTrue($this->isAlreadyApplied("mysql", "PDOStatement: Duplicate column name 'c1'"));
        $this->assertTrue($this->isAlreadyApplied("mysql", "PDOStatement: Duplicate key name 'idx'"));
        $this->assertTrue($this->isAlreadyApplied("mysql", "PDOStatement: Table 't' already exists"));
        $this->assertTrue(
            $this->isAlreadyApplied("mysql", "PDOStatement: Duplicate foreign key constraint name 'fk'")
        );
        // Data-dependent and other errors: must still abort.
        $this->assertFalse(
            $this->isAlreadyApplied("mysql", "PDOStatement: Duplicate entry '1' for key 'PRIMARY'")
        );
        $this->assertFalse(
            $this->isAlreadyApplied("mysql", "PDOStatement: You have an error in your SQL syntax")
        );
        $this->assertFalse(
            $this->isAlreadyApplied("mysql", "PDOStatement: Unknown column 'nope' in 'field list'")
        );
        // Other drivers: never tolerated (SQLite migrations are atomic).
        $this->assertFalse($this->isAlreadyApplied("sqlite", "PDOStatement: Duplicate column name 'c1'"));
    }

    public function testPartialMySqlMigrationCanBeRetriedWithoutCleanup(): void
    {
        $f3 = \Base::instance();
        $db = new FakeMySqlForMigrationTest();
        // First attempt fails on the second statement.
        $db->failOnce = ["THIS IS NOT VALID SQL" => "You have an error in your SQL syntax near 'THIS'"];
        $f3->set("db.instance", $db);

        $this->writeMigration(
            "ALTER TABLE `t` ADD COLUMN `c1` INT NULL;\n" .
            "THIS IS NOT VALID SQL;\n" .
            "UPDATE `config` SET `value` = '99.99.99' WHERE `attribute` = 'version';\n"
        );

        $security = \Helper\Security::instance();
        $this->assertFalse($security->updateDatabase("99.99.99"));
        $this->assertStringContainsString("failed", (string) $f3->get("error"));
        // The first DDL statement stayed applied (MySQL implicit commit).
        $this->assertCount(1, $db->applied);

        // The fixed migration is retried as-is on the next request: the
        // already-applied statement replays and must be tolerated.
        $this->writeMigration(
            "ALTER TABLE `t` ADD COLUMN `c1` INT NULL;\n" .
            "ALTER TABLE `t` ADD COLUMN `c2` INT NULL;\n" .
            "UPDATE `config` SET `value` = '99.99.99' WHERE `attribute` = 'version';\n"
        );
        $f3->clear("error");

        $this->assertTrue($security->updateDatabase("99.99.99"));
        $this->assertSame(" Database updated to version: 99.99.99", $f3->get("success"));
        // c1 was applied exactly once; c2 and the version update ran on retry.
        $this->assertCount(3, $db->applied);
    }

    public function testRealMySqlErrorsStillAbortTheMigration(): void
    {
        $f3 = \Base::instance();
        $db = new FakeMySqlForMigrationTest();
        // A data-dependent failure must never be mistaken for replayed DDL.
        $db->failOnce = ["INSERT" => "Duplicate entry '1' for key 'PRIMARY'"];
        $f3->set("db.instance", $db);

        $this->writeMigration(
            "ALTER TABLE `t` ADD COLUMN `c1` INT NULL;\n" .
            "INSERT INTO `t` (`id`) VALUES (1);\n" .
            "UPDATE `config` SET `value` = '99.99.99' WHERE `attribute` = 'version';\n"
        );

        $this->assertFalse(\Helper\Security::instance()->updateDatabase("99.99.99"));
        $this->assertStringContainsString("Duplicate entry", (string) $f3->get("error"));
    }

    public function testSqliteMigrationStaysAtomic(): void
    {
        $f3 = \Base::instance();
        $dbFile = $f3->get("TEMP") . "migration-test.sqlite";
        @unlink($dbFile);
        $db = new \Helper\SQL("sqlite:" . $dbFile);
        $db->exec("CREATE TABLE `config` (`attribute` TEXT PRIMARY KEY, `value` TEXT)");
        $db->exec("INSERT INTO `config` VALUES ('version', '00.00.00')");
        $f3->set("db.instance", $db);

        try {
            // A failing migration rolls back entirely on SQLite.
            $this->writeMigration(
                "CREATE TABLE `migtest` (`id` INTEGER PRIMARY KEY);\n" .
                "THIS IS NOT VALID SQL;\n" .
                "UPDATE `config` SET `value` = '99.99.99' WHERE `attribute` = 'version';\n"
            );
            $this->assertFalse(\Helper\Security::instance()->updateDatabase("99.99.99"));
            $tables = $db->exec("SELECT name FROM sqlite_master WHERE type='table' AND name='migtest'");
            $this->assertSame([], $tables);
            $version = $db->exec("SELECT value FROM `config` WHERE `attribute` = 'version'");
            $this->assertSame("00.00.00", $version[0]["value"]);

            // A clean migration applies and bumps the version.
            $this->writeMigration(
                "CREATE TABLE `migtest` (`id` INTEGER PRIMARY KEY);\n" .
                "UPDATE `config` SET `value` = '99.99.99' WHERE `attribute` = 'version';\n"
            );
            $this->assertTrue(\Helper\Security::instance()->updateDatabase("99.99.99"));
            $version = $db->exec("SELECT value FROM `config` WHERE `attribute` = 'version'");
            $this->assertSame("99.99.99", $version[0]["value"]);
        } finally {
            @unlink($dbFile);
        }
    }
}
