Phproject
===========
*A lightweight project management system in PHP*


### Requirements
- PHP 5.3 or later
- bcrypt extension
- PDO extension
- GD extension
- MySQL/MariaDB server
- Web server with support for URL rewrites (Apache .htaccess file and nginx sample configuration included)

### Installation
1. Create a database on your MySQL server
2. Import the database.sql file into your new database
3. Copy config-base.ini to config.ini
4. Update config.ini with your database connection details
5. Ensure the tmp/, tmp/cache/, and log/ directories exist and are writable by the web server

### Additional setup
- DEBUG in config.ini supports levels 0-3, with 3 being the most verbose. You should always use 0 in a production environment!
- Phproject is fast, but you can significantly increase performance by installing an op code caching layer like APC. Using APC also greatly increases the speed of temporary cached data, including minified code and common database queries.

### Updating
Simply pulling the repo again should be safe for updates. If database.sql has been modified, you will need to merge the changes into your database as Phproject doesn't yet have a database upgrade system built in. If something breaks after updating, clearing the tmp/ and tmp/cache/ directories of everything except .gitignore will usually solve the problem.

### Customization
Phproject's UI is built around Twitter Bootstrap 3, and is compatible with customized Bootstrap styles including Bootswatch. Simply change the site.theme entry in config.ini to the web path of a Bootstrap CSS file and it will replace the main CSS. Phproject's additions to the Bootstrap core are designed to add features without breaking any existing components, so unless your customized Bootstrap is very heavily modified, everything should continue to work consistently.

To give your site a custom title and meta description, update the site.name and site.description entries in config.ini.

You can also customize the default user image that is shown when a user does not have a Gravatar (Phproject uses mm/mysterman by default) as well as the maximum content rating of Gravatars to show (pg by default). The gravatar.default and gravatar.rating entries in config.ini can be updated to change these.

### Internal details
Phproject uses the Fat Free Framework as it's base, allowing it to have a simple but powerful feature set without compromising performance. Every template file is compiled at run time, and only needs to be recompiled when the code is changed. Phproject includes internal caching that prevents duplicate or bulk database queries from being used, greatly improving performance on large pages with lots of data. This caching will only work if the tmp/ directory is writable, and does not yet support using APC to cache temporary data directly.
