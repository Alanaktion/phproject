<?php

namespace Helper;

class Plugin extends \Prefab {

	protected
			$_hooks   = array(),
			$_nav     = array(),
			$_jsCode  = array(),
			$_jsFiles = array();

	/**
	 * Add a hook entry
	 * @param string   $hook
	 * @param callable $action
	 */
	public function addHook($hook, callable $action) {
		if(isset($this->_hooks[$hook])) {
			$this->_hooks[$hook][] = $callable;
		} else {
			$this->_hooks[$hook] = array($callable);
		}
	}

	/**
	 * Add a navigation item
	 * @param string $href
	 * @param string $title
	 * @param string $match
	 */
	public function addNavItem($href, $title, $match = null) {
		$this->_nav[] = array(
			"href"  => $href,
			"title" => $title,
			"match" => $match
		);
	}

	/**
	 * Add JavaScript code
	 * @param string $code
	 * @param string $match
	 */
	public function addJsCode($code, $match = null) {
		$this->_jsCode[] = array(
			"code"  => $code,
			"match" => $match
		);
	}

	/**
	 * Add a JavaScript file
	 * @param string $file
	 * @param string $match
	 */
	public function addJsFile($file, $match = null) {
		$_nav[] = array(
			"file"  => $file,
			"match" => $match
		);
	}

	/**
	 * Get navbar items, optionally setting matching items as active
	 * @param  string $path
	 * @return array
	 */
	public function getNav($path = null) {
		$return = $this->_nav;
		foreach($return as &$item) {
			if($item["match"] && $path && preg_match($item["match"], $path)) {
				$item["active"] = true;
			} else {
				$item["active"] = false;
			}
		}
		return $return;
	}

}
