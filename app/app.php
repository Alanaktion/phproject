<?php

/**
 * Phproject core application class
 * @package Phproject
 * @version 1.0.0
 * @license GPL 3
 */
class App {

	/**
	 * Get the F3 Base class instance
	 * @return Base
	 */
	public static function f3() {
		return \Base::instance();
	}

	/**
	 * Create a new model instance, optionally passing additional parameters
	 * @param  string $name
	 * @return Model
	 */
	public static function model($name) {
		$name = self::buildClassName("model", $name);
		$args = array_shift(func_get_args());
		if($args) {
			$class = new ReflectionClass($name);
			return $class->newInstanceArgs($args);
		} else {
			return new $name;
		}
	}

	/**
	 * Get a helper instance, optionally passing additional parameters
	 * @param  string $name
	 * @return Model
	 */
	public static function helper($name) {
		$name = self::buildClassName("helper", $name);

		// Return prefab instance
		if(is_callable(array($name, "instance"))) {
			return $name::instance();
		}

		// Return new instance with extra params passed, if any
		$args = array_shift(func_get_args());
		if($args) {
			$class = new ReflectionClass($name);
			return $class->newInstanceArgs($args);
		} else {
			return new $name;
		}

	}

	/**
	 * Build a valid class name from an object type and friendly name
	 * @param  string $type 'model' or 'helper'
	 * @param  string $name
	 * @return string
	 */
	protected static function buildClassName($type, $name) {
		$class = "\\" . ucfirst($type) . "\\";
		$class .= str_replace(" ", "\\", ucwords(str_replace(array("/", "\\"), " ", $name)));
		return $class;
	}

}
