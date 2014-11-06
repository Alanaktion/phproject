<?php

abstract class Plugin extends \Prefab {

	/**
	 * Initialize the plugin including any hooks
	 */
	abstract public function _load();

	/**
	 * Runs installation code required for plugin, if any is required
	 *
	 * This method is called if _installed() returns false
	 */
	public function _install() {}

	/**
	 * Checks if the plugin is installed
	 *
	 * The return value of this method should be cached when possible
	 * @return bool
	 */
	abstract public function _installed();

	/**
	 * Hook into a core feature
	 * This is the primary way for plugins to add functionality
	 * @see    http://www.phproject.org/plugins.html
	 * @param  string   $hook
	 * @param  callable $action
	 * @return Plugin
	 */
	final protected function _hook($hook, callable $action) {
		\Helper\Plugin::instance()->addHook($hook, $action);
		return $this;
	}

	/**
	 * Add a link to the navigation bar
	 * @param string $href
	 * @param string $title
	 * @param string $match Optional regex, will highlight if the URL matches
	 * @return Plugin
	 */
	final protected function _addNav($href, $title, $match = null) {
		\Helper\Plugin::instance()->addNavItem($href, $title, $match);
		return $this;
	}

	/**
	 * Include JavaScript code or file
	 * @param string $value Code or file path
	 * @param string $type  Whether to include as "code" or a "file"
	 * @param string $match Optional regex, will include if the URL matches
	 * @return Plugin
	 */
	final protected function _addJs($value, $type = "code", $match = null) {
		if($type == "file") {
			\Helper\Plugin::instance()->addJsFile($value, $match);
		} else {
			\Helper\Plugin::instance()->addJsCode($value, $match);
		}
		return $this;
	}

	/**
	 * Get current time and date in a MySQL NOW() format
	 * @param  boolean $time  Whether to include the time in the string
	 * @return string
	 */
	final public function now($time = true) {
		return $time ? date("Y-m-d H:i:s") : date("Y-m-d");
	}

}
