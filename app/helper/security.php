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
            return array(
                "salt" => $salt,
                "hash" => sha1($salt . sha1($string))
            );
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
        return md5($this->randBytes(64));
    }

    /**
     * Generate a secure SHA1 salt for hasing
     * @return string
     */
    public function salt_sha1()
    {
        return sha1($this->randBytes(64));
    }

    /**
     * Generate a secure SHA-256/384/512 salt
     * @param  integer $size 256, 384, or 512
     * @return string
     */
    public function salt_sha2($size = 256)
    {
        $allSizes = array(256, 384, 512);
        if (!in_array($size, $allSizes)) {
            throw new \Exception("Hash size must be one of: " . implode(", ", $allSizes));
        }
        return hash("sha$size", $this->randBytes(512), false);
    }

    /**
     * Generate secure random bytes
     * @param  integer $length
     * @return binary
     */
    public function randBytes($length = 16)
    {
        // Try to use native secure random
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        // Fall back to OpenSSL cryptography extension if available
        if (function_exists("openssl_random_pseudo_bytes")) {
            $strong = false;
            $rnd = openssl_random_pseudo_bytes($length, $strong);
            if ($strong === true) {
                return $rnd;
            }
        }

        // Use SHA256 of mt_rand if OpenSSL is not available
        $rnd = "";
        for ($i = 0; $i < $length; $i++) {
            $sha = hash("sha256", mt_rand());
            $char = mt_rand(0, 30);
            $rnd .= chr(hexdec($sha[$char] . $sha[$char + 1]));
        }
        return (binary)$rnd;
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
            $db->exec(explode(";", $update_db));
            \Cache::instance()->reset();
            $f3->set("success", " Database updated to version: {$version}");
        } else {
            $f3->set("error", " Database file not found for version: {$version}");
        }
    }

    /**
     * Check if two hashes are equal, safe against timing attacks
     *
     * This is a userland implementation of the hash_equals function from 5.6
     *
     * @param  string $str1
     * @param  string $str2
     * @return boolean
     */
    public function hashEquals($str1, $str2)
    {
        if (strlen($str1) != strlen($str2)) {
            return false;
        } else {
            $res = $str1 ^ $str2;
            $ret = 0;
            for ($i = strlen($res) - 1; $i >= 0; $i--) {
                $ret |= ord($res[$i]);
            }
            return !$ret;
        }
    }
}
