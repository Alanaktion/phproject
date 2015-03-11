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
	 * @param string $location
	 */
	public function addNavItem($href, $title, $match = null, $location = 'root') {
		$this->_nav[] = array(
			"href"  => $href,
			"title" => $title,
			"match" => $match,
			"location" => $location
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
		$this->_jsFiles[] = array(
			"file"  => $file,
			"match" => $match
		);
	}

	/**
	 * Get navbar items, optionally setting matching items as active
	 * @param  string $path
	 * @param  string $location
	 * @return array
	 */
	public function getNav($path = null, $location = "root") {
		$all = $this->_nav;
		$return = array();
		foreach($all as $item) {
			if($item['location'] == $location) {
				$return[] = $item + array("active" => ($item["match"] && $path && preg_match($item["match"], $path)));
			}
		}
		return $return;
	}

	/**
	 * Get a multidimensional array of all nav items by location
	 * @param  string $path
	 * @return array
	 */
	public function getAllNavs($path = null) {
		return array(
			"root" => $this->getNav($path, "root"),
			"user" => $this->getNav($path, "user"),
			"new" => $this->getNav($path, "new"),
			"browse" => $this->getNav($path, "browse")
		);
	}

}
