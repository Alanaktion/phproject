<?php

namespace Helper;

class Security extends \Prefab {

	/**
	 * Generate a salted SHA1 hash
	 * @param  string $string
	 * @param  string $salt
	 * @return array|string
	 */
	public function hash($string, $salt = null) {
		if($salt === null) {
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
	public function salt() {
		return md5($this->rand_bytes(64));
	}

	/**
	 * Generate a secure SHA1 salt for hasing
	 * @return string
	 */
	public function salt_sha1() {
		return sha1($this->rand_bytes(64));
	}

	/**
	 * Generate a secure SHA-256/384/512 salt
	 * @param  integer $size 256, 384, or 512
	 * @return string
	 */
	public function salt_sha2($size = 256) {
		$allSizes = array(256, 384, 512);
		if(!in_array($size, $allSizes)) {
			throw new Exception("Hash size must be one of: " . implode(", ", $allSizes));
		}
		return hash("sha$size", $this->rand_bytes(512), false);
	}

	/**
	 * Encrypt a string with ROT13
	 * @param  string $string
	 * @return string
	 */
	public function rot13($string) {
		return str_rot13($string);
	}

	/**
	 * ROT13 equivelant for hexadecimal
	 * @param  string $hex
	 * @return string
	 */
	public function rot8($hex) {
		return strtr(
			strtolower($hex),
			array(
				"0"=>"8",
				"1"=>"9",
				"2"=>"a",
				"3"=>"b",
				"4"=>"c",
				"5"=>"d",
				"6"=>"e",
				"7"=>"f",
				"8"=>"0",
				"9"=>"1",
				"a"=>"2",
				"b"=>"3",
				"c"=>"4",
				"d"=>"5",
				"e"=>"6",
				"f"=>"7"
			)
		);
	}

	/**
	 * Generate secure random bytes
	 * @param  integer $length
	 * @return binary
	 */
	private function rand_bytes($length = 16) {

		// Use OpenSSL cryptography extension if available
		if(function_exists("openssl_random_pseudo_bytes")) {
			$strong = false;
			$rnd = openssl_random_pseudo_bytes($length, $strong);
			if($strong === true) {
				return $rnd;
			}
		}

		// Use SHA256 of mt_rand if OpenSSL is not available
		$rnd = "";
		for($i = 0; $i < $length; $i++) {
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
	public function checkDatabaseVersion() {
		$f3 = \Base::instance();

		// Get current version
		$version = $f3->get("version");
		if(!$version) {
			$result = $f3->get("db.instance")->exec("SELECT value as version FROM config WHERE attribute = 'version'");
			$version = $result[0]["version"];
		}

		// Check available versions
		$db_files = scandir("db");
		foreach ($db_files as $file) {
			$file = substr($file, 0, -4);
			if(version_compare($file, $version) > 0) {
				return $file;
			}
		}

		return true;
	}

	/**
	 * Install latest core database updates
	 * @param string $version
	 */
	public function updateDatabase($version) {
		$f3 = \Base::instance();
		if(file_exists("db/{$version}.sql")) {
			$update_db = file_get_contents("db/{$version}.sql");
			$db = $f3->get("db.instance");
			$db->exec(explode(";", $update_db));
			\Cache::instance()->reset();
			$f3->set("success", " Database updated to version: {$version}");
		} else {
			$f3->set("error", " Database file not found for version: {$version}");
		}
	}

}
