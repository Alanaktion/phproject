<?php

namespace Model;

class Session extends \Model {

	protected
		$_table_name = "session",
		$cookie_name = "phproj_token";

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
		$token = $f3->get("COOKIE.{$this->cookie_name}");
		if($token) {
			$this->load(array("token = ?", $token));
			$expire = $f3->get("JAR.expire");


			// Delete expired sessions
			if(time() - $expire > strtotime($this->created)) {
				$this->delete();
				return $this;
			}

			// Update nearly expired sessions
			if(time() - $expire / 2 > strtotime($this->created)) {
				$this->created = date("Y-m-d H:i:s");
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
		$f3->set("COOKIE.{$this->cookie_name}", $this->token, $f3->get("JAR.expire"));
		return $this;
	}

	/**
	 * Delete the session
	 * @return Session
	 */
	public function delete() {

		// Empty the session cookie if it matches the current token
		$f3 = \Base::instance();
		if($this->token = $f3->get("COOKIE.{$this->cookie_name}")) {
			$f3->set("COOKIE.{$this->cookie_name}", "");
		}

		// Delete the session row
		parent::delete();

		return $this;
	}

}

