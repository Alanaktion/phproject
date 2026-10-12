<?php

declare(strict_types=1);

/**
 * Fake DB handle mimicking F3's DB\SQL exception format and MySQL's
 * implicit-commit DDL semantics: statements applied before a failure
 * stay applied (no rollback of DDL), and replaying one throws the same
 * \Exception('PDOStatement: ...') F3 produces from errorInfo()[2].
 */
class FakeMySqlForMigrationTest
{
    /** @var string[] statements "applied" so far */
    public array $applied = [];
    /** @var array<string,string> needle => one-time failure message */
    public array $failOnce = [];

    public function driver(): string
    {
        return "mysql";
    }

    public function begin(): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function rollback(): bool
    {
        return true;
    }

    public function pdo(): object
    {
        return new class {
            public function inTransaction(): bool
            {
                return true;
            }
        };
    }

    public function exec(string $stmt)
    {
        $stmt = trim($stmt);
        foreach ($this->failOnce as $needle => $message) {
            if (str_contains($stmt, $needle)) {
                unset($this->failOnce[$needle]);
                // Statements applied before this one stay applied:
                // MySQL implicitly committed them.
                throw new \Exception("PDOStatement: " . $message);
            }
        }
        if (in_array($stmt, $this->applied, true)) {
            // Replay of already-applied DDL, exactly as F3 surfaces it.
            throw new \Exception("PDOStatement: " . $this->duplicateMessage($stmt));
        }
        $this->applied[] = $stmt;
        return 0;
    }

    private function duplicateMessage(string $stmt): string
    {
        if (str_contains($stmt, "ADD COLUMN")) {
            return "Duplicate column name 'c1'";
        }
        if (str_contains($stmt, "CREATE TABLE")) {
            return "Table 't' already exists";
        }
        return "Duplicate key name 'k'";
    }
}

