Phproject plugins should be placed in this directory.

A plugin can either be a single PHP file containing a single class, or a directory containing a `base.php` file, which includes the main class.

Plugins must have a _load() method in the main class, which returns the hooks the plugin will use, as well as it's metadata.

Plugins should have a comment block at the start of the file containing at least the plugin name and developer's name, in a standard, easily parsed format.

Plugins may have an _installed() method which returns a boolean value specifying whether the plugin has been installed correctly. This should include checks such as required database tables and server configuration options, and should cache a `TRUE` result using Fat Free's caching abstraction layer to improve performance on subsequent requests after installation has succeeded.

Plugins may have a routes.ini file if they are in a directory, which will be automatically loaded if the plugin's _installed() method returns `TRUE`, which must route plugin-specific paths to the correct actions within the plugin's main class or sub-classes.

Plugins may have a database.sql file if they are in a directory, which will be automatically loaded if the plugin's _installed() method returns `FALSE`, which must include the SQL queries to add the required database tables and rows for the plugin to operate.
