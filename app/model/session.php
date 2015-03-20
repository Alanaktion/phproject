<?php

namespace Model;

class Session extends \Model {

	protected $_table_name = "session";

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
			$this->key = \Helper\Security::instance()->salt_sha2(512);
			if($auto_save) {
				$this->save();
			}
		}

	}

}

