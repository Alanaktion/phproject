<?php

namespace Model;

/**
 * Class Session
 *
 * @property int $id
 * @property string $token
 * @property string $ip
 * @property int $user_id
 * @property string $created
 */
class Session extends \Model {

	protected $_table_name = "session";
	const COOKIE_NAME = "phproj_token";

	/**
	 * Create a new session
	 * @param int $user_id
	 * @param bool $auto_save
	 */
	public function __construct($user_id = null, $auto_save = true) {

		// Run model constructor
		parent::__construct();

		if($user_id !== null) {
			$this->user_id = $user_id;
			$this->token = \Helper\Security::instance()->salt_sha2();
			$this->ip = \Base::instance()->get("IP");
			$this->created = date("Y-m-d H:i:s");
			if($auto_save) {
				$this->save();
			}
		}

	}

	/**
	 * Load the current session
	 * @return Session
	 */
	public function loadCurrent() {
		$f3 = \Base::instance();
		$ip = $f3->get("IP");
		$token = $f3->get("COOKIE.".self::COOKIE_NAME);
		if($token) {
			$this->load(array("token = ? AND ip = ?", $token, $ip));
			$expire = $f3->get("JAR.expire");

			// Delete expired sessions
			if(time() - $expire > strtotime($this->created)) {
				$this->delete();
				return $this;
			}

			// Update nearly expired sessions
			if(time() - $expire / 2 > strtotime($this->created)) {
				if($f3->get("DEBUG")) {
					$log = new \Log("session.log");
					$log->write("Updating expiration: " . json_encode($this->cast())
							. "; new date: " . date("Y-m-d H:i:s"));
				}
				$this->created = date("Y-m-d H:i:s");
				$this->save();
				$this->setCurrent();
			}
		}
		return $this;
	}

	/**
	 * Set the user's cookie to the current session
	 * @return Session
	 */
	public function setCurrent() {
		$f3 = \Base::instance();

		if($f3->get("DEBUG")) {
			$log = new \Log("session.log");
			$log->write("Setting current session: " . json_encode($this->cast()));
		}

		$f3->set("COOKIE.".self::COOKIE_NAME, $this->token, $f3->get("JAR.expire"));
		return $this;
	}

	/**
	 * Delete the session
	 * @return Session
	 */
	public function delete() {
		if(!$this->id) {
			return $this;
		}

		$f3 = \Base::instance();

		if($f3->get("DEBUG")) {
			$log = new \Log("session.log");
			$log->write("Deleting session: " . json_encode($this->cast()));
		}

		// Empty the session cookie if it matches the current token
		if($this->token == $f3->get("COOKIE.".self::COOKIE_NAME)) {
			$f3->set("COOKIE.".self::COOKIE_NAME, "");
		}

		// Delete the session row
		parent::delete();

		return $this;
	}

}

