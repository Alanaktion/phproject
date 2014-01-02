<?php

namespace Helper;

class Security extends \Prefab {

	// bcrypt is bundled with PHP 5.4 or later by default
	public function bcrypt($string, $work = 13) {
		$salt = strtr($this->base64_salt(22), "+", ".");
		$salt = sprintf("$2y$%s$%s", $work, $salt);
		$hash = crypt($string, $salt);
		if(strlen($hash) > 13) {
			return $hash;
		} else {
			return false;
		}
	}

	public function bcrypt_verify($hash, $string) {
		return (crypt($string,$hash) === $hash);
	}

	public function rot13($string) {
		return str_rot13($string);
	}

	// rot13 for hexadecimal
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

	public function rand_bytes($length = 16) {

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

	private function base64_salt($length = 22) {
		$character_list = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789+/";
		$salt = "";
		for($i = 0; $i < $length; $i++) {
			$salt .= $character_list{mt_rand(0, (strlen($character_list) - 1))};
		}
		return $salt;
	}

}
