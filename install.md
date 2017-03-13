---
layout: md
title: Install
---
<h1 class="page-header">Install</h1>

## Requirements

* PHP 5.6 or later (PHP 5.4 and 5.5 currently work but are not officially supported)
    * MySQL PDO extension
    * GD extension (recommended)
* MySQL 5 Server
* Web server with support for URL rewrites
    * Apache .htaccess file and nginx sample configuration included
    * Lighttpd and IIS 7+ should work, but will require custom configuration


## Installation

The installation process for Phproject should be reasonably simple compared with Redmine or other project management systems.

1. Download the [latest stable release](https://github.com/Alanaktion/phproject/releases/latest) or clone the git repository and optionally checkout the release branch
2. Extract the zip archive to a web-accessible directory with write access
3. Create a database on your MySQL server
4. Navigate to the application and complete the installation

## Additional Setup
Once installed, many additional options for configuring your site can be found in the Administration panel, under the Configuration tab. Advanced users can add entries to the `config` database table to modify additional configuration, using options from the `config-base.ini` file.

To see performance information and additional details about errors, change `DEBUG` in `config.ini`. This option supports levels 0-3, with 3 being the most verbose.
<span class="text-danger">You should always use 0 in a production environment!</span>

Phproject is designed to be fast, but you can still increase performance by installing an opcode caching layer like APC. Using APC also greatly increases the speed of temporary cached data, including minified code and heavy, cacheable database queries.


## Updating
If you installed Phproject with git, simply run `git pull` to update. Otherwise, updates can be manually installed by downloading the [latest release](https://github.com/Alanaktion/phproject/releases/latest) and extracting it over your existing installation.

If there is a change to the database needed after you've updated, you will see an alert in the Administration on your Phproject installation, and can update the database from there.

If something breaks after updating, clearing the APC cache or emptying the `tmp/cache/` directory will usually solve the problem.
