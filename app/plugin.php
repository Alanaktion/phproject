<?php

abstract class Plugin {

	/**
	 * Initialize the plugin including any hooks
	 * @return array Required app hooks
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
	 * @param  string   $hook
	 * @param  callable $action
	 * @see    http://www.phproject.org/plugins.html
	 */
	final protected function _hook($hook, callable $action) {
		\Base::instance()->set("_hooks.$hook", $action);
	}

	/**
	 * Add a link to the navigation bar
	 * @param string $href
	 * @param string $match Optional regex, will highlight if the URL matches
	 */
	final protected function _addNav($href, $match = null) {

	}

	/**
	 * [_addJs description]
	 * @param string $value Code or file path
	 * @param string $type  Whether to include as "code" or a "path"
	 * @param string $match Optional regex, will include if the URL matches
	 */
	final protected function _addJs($value, $type = "code", $match = null) {

	}

}
