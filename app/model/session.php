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
			$f3 = \Base::instance();
			$this->user_id = $user_id;
			$this->token = \Helper\Security::instance()->salt_sha2();
			$this->ip = \Base::instance()->get("IP");
			$this->created = date("Y-m-d H:i:s");
			if($auto_save) {
				$this->save();
				if($f3->get("DEBUG")) {
					$log = new \Log("session.log");
					$log->write("Created session with autosave: " . json_encode($this->cast()));
				}
			} else {
				if($f3->get("DEBUG")) {
					$log = new \Log("session.log");
					$log->write("Created session without autosave: " . json_encode($this->cast()));
				}
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
		$token = $f3->get("COOKIE.{$this->cookie_name}");
		if($token) {
			$this->load(array("token = ? AND ip = ?", $token, $ip));
			$expire = $f3->get("JAR.expire");

			if($f3->get("DEBUG")) {
				$log = new \Log("session.log");
				$log->write("Loaded session: " . json_encode($this->cast()));
			}

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

		if($f3->get("DEBUG")) {
			$log = new \Log("session.log");
			$log->write("Setting current session: " . json_encode($this->cast()));
		}

		$f3->set("COOKIE.{$this->cookie_name}", $this->token, $f3->get("JAR.expire"));
		return $this;
	}

	/**
	 * Delete the session
	 * @return Session
	 */
	public function delete() {
		$f3 = \Base::instance();

		if($f3->get("DEBUG")) {
			$log = new \Log("session.log");
			$log->write("Deleting session: " . json_encode($this->cast()));
		}

		// Empty the session cookie if it matches the current token
		if($this->token = $f3->get("COOKIE.{$this->cookie_name}")) {
			$f3->set("COOKIE.{$this->cookie_name}", "");
		}

		// Delete the session row
		parent::delete();

		return $this;
	}

}

