Phproject plugins should be placed in this directory.

* Plugins must have a `Base` class in `base.php` that extends `\Plugin`
    * This class should contain all core code for the plugin, including all methods and properties in the Plugin Standards
* Plugins must have a `_load()` method which is called when initializing the plugin
    * Any hooks and routes used in the plugin should be initialized in this method
* Plugins must have a PHPDoc comment block at the start of the `base.php` file
    * Block must contain at least the @package tag
    * Block should contain the @author tag
* Plugins must have an `_installed()` method which will be called to check the installation status of the plugin
* Plugins may have an `_install()` method which will be called if `_installed()` returns `false`
* Plugins may have a `dict` directory, which will be loaded for localization
* Plugins should follow the [Code Standards](http://www.phproject.org/contribute.html)
