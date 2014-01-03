OpenProject
===========
*A lightweight project management system in PHP*


### Requirements
- PHP 5.3 or later
- bcrypt extension
- PDO extension
- GD extension
- MySQL/MariaDB server
- Web server with support for URL rewrites (Apache .htaccess file included)

### Installation
1. Create a database on your MySQL server
2. Import the database.sql file into your new database
3. Copy config-base.ini to config.ini
4. Update config.ini with your database connection details
5. Ensure tmp/, tmp/cache/, and log/ directories exist and are writable by the web server

### Additional setup
- DEBUG in config.ini supports levels 1-3, with 3 being the most verbose. Use 1 in a production environment.
- OpenProject is fast, but you can significantly increase performance by installing an op code caching layer like APC

### Internal details
OpenProject uses the Fat Free Framework as it's base, allowing it to have a simple but powerful feature set without compromising performance. Every template file is compiled at run time, and only needs to be recompiled when the code is changed. OpenProject includes internal caching that prevents duplicate or bulk database queries from being used, greatly improving performance on large pages with lots of data. This caching will only work if the tmp/ directory is writable, and does not yet support using APC to cache temporary data directly.
