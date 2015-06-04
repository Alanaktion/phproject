<?php

abstract class Plugin extends \Prefab {

	// Metadata container
	protected $_meta;

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
	 * Check if plugin is installed
	 *
	 * The return value of this method should be cached when possible
	 * @return bool
	 */
	public function _installed() {
		return true;
	}

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
	 * @param string $location Optional location, valid values: 'root', 'user', 'new', 'browse'
	 * @return Plugin
	 */
	final protected function _addNav($href, $title, $match = null, $location = 'root') {
		\Helper\Plugin::instance()->addNavItem($href, $title, $match, $location);
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

	/**
	 * Get plugin's metadata including author, package, version, etc.
	 * @return array
	 */
	final public function _meta() {
		if($this->_meta) {
			return $this->_meta;
		}

		// Parse class file for phpDoc comments
		$obj = new ReflectionClass($this);
		$str = file_get_contents($obj->getFileName());
		preg_match_all("/\\s+@(package|author|version) (.+)/m", $str, $matches, PREG_SET_ORDER);

		// Build meta array from phpDoc comments
		$meta = array();
		foreach($matches as $match) {
			$meta[$match[1]] = trim($match[2]);
		}

		$this->_meta = $meta + array("package" => str_replace(array("Plugin\\", "\\Base"), "", get_class($this)), "author" => null, "version" => null);
		return $this->_meta;
	}

	/**
	 * Get plugin's package name
	 * @return string
	 */
	final public function _package() {
		$meta = $this->_meta();
		return $meta["package"];
	}

	/**
	 * Get plugin's version number, if any
	 * @return string
	 */
	final public function _version() {
		$meta = $this->_meta();
		return $meta["version"];
	}

}
