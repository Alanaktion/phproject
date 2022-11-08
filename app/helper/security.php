<?php

namespace Helper;

class Security extends \Prefab
{
    /**
     * Generate a salted SHA1 hash
     * @param  string $string
     * @param  string $salt
     * @return array|string
     */
    public function hash($string, $salt = null)
    {
        if ($salt === null) {
            $salt = $this->salt();
            return [
                "salt" => $salt,
                "hash" => sha1($salt . sha1($string)),
            ];
        } else {
            return sha1($salt . sha1($string));
        }
    }

    /**
     * Generate a secure salt for hashing
     * @return string
     */
    public function salt()
    {
        return md5(random_bytes(64));
    }

    /**
     * Generate a secure SHA1 salt for hashing
     * @return string
     */
    public function salt_sha1()
    {
        return sha1(random_bytes(64));
    }

    /**
     * Generate a secure SHA-256/384/512 salt
     * @param  integer $size 256, 384, or 512
     * @return string
     */
    public function salt_sha2($size = 256)
    {
        $allSizes = [256, 384, 512];
        if (!in_array($size, $allSizes)) {
            throw new \Exception("Hash size must be one of: " . implode(", ", $allSizes));
        }
        return hash("sha$size", random_bytes(512), false);
    }

    /**
     * Generate secure random bytes
     * @deprecated
     * @param  integer $length
     * @return string
     */
    public function randBytes($length = 16)
    {
        return random_bytes($length);
    }

    /**
     * Check if the database is the latest version
     * @return bool|string TRUE if up-to-date, next version otherwise.
     */
    public function checkDatabaseVersion()
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
     * Install latest core database updates
     * @param string $version
     */
    public function updateDatabase($version)
    {
        $f3 = \Base::instance();
        if (file_exists("db/{$version}.sql")) {
            $update_db = file_get_contents("db/{$version}.sql");
            $db = $f3->get("db.instance");
            foreach (explode(";", $update_db) as $stmt) {
                $db->exec($stmt);
            }
            \Cache::instance()->reset();
            $f3->set("success", " Database updated to version: {$version}");
        } else {
            $f3->set("error", " Database file not found for version: {$version}");
        }
    }

    /**
     * Initialize a CSRF token
     */
    public function initCsrfToken()
    {
        $f3 = \Base::instance();
        if (!($token = $f3->get('COOKIE.XSRF-TOKEN'))) {
            $token = $this->salt_sha2();
            $f3->set('COOKIE.XSRF-TOKEN', $token);
        }
        $f3->set('csrf_token', $token);
    }

    /**
     * Validate a CSRF token, exiting if invalid
     */
    public function validateCsrfToken()
    {
        $f3 = \Base::instance();
        $cookieToken = $f3->get('COOKIE.XSRF-TOKEN');
        $requestToken = $f3->get('POST.csrf-token');
        if (!$requestToken) {
            $requestToken = $f3->get('HEADERS.X-Xsrf-Token');
        }
        if (!$cookieToken || !$requestToken || !hash_equals($cookieToken, $requestToken)) {
            $f3->error(400, 'Invalid CSRF token');
            exit;
        }
    }

    /**
     * Check if two hashes are equal, safe against timing attacks
     *
     * @deprecated Use the native PHP implementation instead.
     *
     * @param  string $str1
     * @param  string $str2
     * @return boolean
     */
    public function hashEquals($str1, $str2)
    {
        return hash_equals($str1, $str2);
    }
}
