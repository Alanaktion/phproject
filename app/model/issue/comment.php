<?php

namespace Model\Issue;

class Comment extends \Model\Base {

	protected $_table_name = "issue_comment";

	/**
	 * Save the comment
	 * @return Comment
	 */
	public function save() {
		$f3 = \Base::instance();

		// Censor credit card numbers if enabled
		if($f3->get("security.block_ccs")) {
			if(preg_match("/[0-9-]{9,15}[0-9]{4}/", $this->get("text"))) {
				$this->set("text", preg_replace("/[0-9-]{9,15}([0-9]{4})/", "************$1", $this->get("text")));
			}
		}

		return parent::save();
	}

}

