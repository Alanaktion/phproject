<?php

namespace Helper;

use Helper\Security\AntiXss;

class Security extends \Prefab
{
    /**
     * Hash a password using bcrypt via password_hash().
     * Falls back to legacy SHA1 hashing when a salt is provided
     * for verifying existing passwords during migration.
     */
    public function hash(string $string, ?string $salt = null): array|string
    {
        if ($salt === null) {
            // New password: use bcrypt
            $hash = password_hash($string, PASSWORD_BCRYPT);
            return [
                "salt" => "bcrypt",
                "hash" => $hash,
            ];
        }

        if ($salt === "bcrypt") {
            // Already using bcrypt - this path is for verification
            return $string;
        }

        // Legacy SHA1 path for verifying old passwords
        return sha1($salt . sha1($string));
    }

    /**
     * Verify a password against a stored hash.
     * Supports both bcrypt and legacy SHA1 hashes.
     */
    public function verifyPassword(string $password, string $hash, string $salt): bool
    {
        if ($salt === "bcrypt") {
            return password_verify($password, $hash);
        }

        // Legacy SHA1 verification
        return hash_equals($this->hash($password, $salt), $hash);
    }

    /**
     * Generate a secure salt for hashing
     */
    public function salt(): string
    {
        return md5(random_bytes(64));
    }

    /**
     * Generate a secure SHA1 salt for hashing
     */
    public function salt_sha1(): string
    {
        return sha1(random_bytes(64));
    }

    /**
     * Generate a secure SHA-256/384/512 salt
     * @param  int $size 256, 384, or 512
     */
    public function salt_sha2(int $size = 256): string
    {
        $allSizes = [256, 384, 512];
        if (!in_array($size, $allSizes)) {
            throw new \Exception("Hash size must be one of: " . implode(", ", $allSizes));
        }

        return hash("sha{$size}", random_bytes(512), false);
    }

    /**
     * Check if the database is the latest version
     * @return bool|string TRUE if up-to-date, next version otherwise.
     */
    public function checkDatabaseVersion(): string|bool
    {
        $f3 = \Base::instance();

        // Get current version
        $version = $f3->get("version");
        if (!$version) {
            $result = $f3->get("db.instance")->exec("SELECT value as version FROM config WHERE attribute = 'version'");
            $version = $result[0]["version"];
        }

        // Check available versions
        $db_files = scandir("db");
        foreach ($db_files as $file) {
            $file = substr($file, 0, -4);
            if (version_compare($file, $version) > 0) {
                return $file;
            }
        }

        return true;
    }

    /**
     * Install a core database update
     * @return bool TRUE if the update was applied, FALSE on failure
     */
    public function updateDatabase(string $version): bool
    {
        $f3 = \Base::instance();
        if (!file_exists("db/{$version}.sql")) {
            $f3->set("error", " Database file not found for version: {$version}");
            return false;
        }

        $update_db = file_get_contents("db/{$version}.sql");
        $db = $f3->get("db.instance");
        try {
            // Note: MySQL implicitly commits around DDL statements, so a
            // failed migration there can't be rolled back; the version row
            // is only updated by the last statement, so a failure leaves
            // the database at the previous version and surfaces an error.
            $db->begin();
            foreach (explode(";", (string) $update_db) as $stmt) {
                if (trim($stmt) !== "") {
                    $db->exec($stmt);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            $f3->set("error", " Database update to version {$version} failed: " . $e->getMessage());
            return false;
        }

        \Cache::instance()->reset();
        $f3->set("success", " Database updated to version: {$version}");
        return true;
    }

    /**
     * Initialize a CSRF token
     */
    public function initCsrfToken(): void
    {
        $f3 = \Base::instance();
        if (!($token = $f3->get('COOKIE.XSRF-TOKEN'))) {
            $token = $this->salt_sha2();
            $f3->set('COOKIE.XSRF-TOKEN', $token);
        }

        $f3->set('csrf_token', $token);
    }

    /**
     * Validate a CSRF token
     */
    public function validateCsrfToken(): void
    {
        $f3 = \Base::instance();
        $cookieToken = $f3->get('COOKIE.XSRF-TOKEN');
        $requestToken = $f3->get('POST.csrf-token');
        if (!$requestToken) {
            $requestToken = $f3->get('HEADERS.X-Xsrf-Token');
        }

        if (!$cookieToken || !$requestToken || !hash_equals($cookieToken, $requestToken)) {
            $f3->error(400, 'Invalid CSRF token');
        }
    }

    /**
     * Clean a string to remove potential XSS attacks
     */
    public function cleanXss(string $str): string
    {
        return (new AntiXss())->xss_clean($str);
    }
}
