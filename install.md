---
layout: md
title: Install
---
<h1 class="page-header">Install</h1>

## Requirements

* PHP 7.0 or later (PHP 7.4 recommended)
    * MySQL PDO extension
    * GD extension (required for thumbnails and avatars)
* MySQL 5.6 or later (MySQL 8 recommended)
* Web server with support for URL rewrites
    * Apache .htaccess file and nginx sample configuration included
    * Caddy, H2O, Lighttpd and IIS should all work, but will require custom configuration


## Installation

The installation process for Phproject should be reasonably simple compared with other project management systems.

1. Download the [latest stable release](https://github.com/Alanaktion/phproject/releases/latest)
2. Extract the zip archive to a web-accessible directory with write access
3. Create a database on your MySQL server
4. Navigate to the application in a web browser and complete the installation

### Command-line installation

If you have command-line access to the project files, you can complete the installation via command-line rather than through the web UI. This can be helpful for automating the site setup, for example if you need multiple sites or are building automated test environments.

```bash
php install.php \
    --site-url=https://phproject.example.com/ \
    --site-name="Example Site" \
    --db-user=phproject \
    --db-name=example \
    --admin-username=admin \
    --admin-email=admin@example.com \
    --admin-password="secret!"
```

The `--site-url` argument must be the complete URL used to access the site, including the protocol and a trailing slash.

Note that this requires the CLI user to have write permissions to the Phproject installation directory, so it may be best to use with _e.g._ `sudo -u www-data`.

Use `-h` or `--help` to see all available arguments:

```bash
php install.php --help
```

### Git installation

If you are installing directly from the Git repository, rather than a release .zip, you'll need to manually install your [Composer](https://getcomposer.org) dependencies before completing the installation:

```bash
git clone https://github.com/Alanaktion/phproject.git
cd phproject
composer install
```

## Additional setup
Once installed, many additional options for configuring your site can be found in the Administration panel, under the Configuration tab. Advanced users can add entries to the `config` database table to modify additional configuration.

To see performance information and additional details about errors, add a `DEBUG` entry to `config.php`. This option supports levels 0-3, with 3 being the most verbose.
<span class="text-danger">You should always use 0 in a production environment!</span>

Phproject is designed to be fast, but you can still increase performance by installing an opcode caching layer like APC. Using APC also greatly increases the speed of temporary cached data, including minified code and heavy, cacheable database queries.


## Updating
If you installed Phproject with git, simply run `git pull` to update. Otherwise, updates can be manually installed by downloading the [latest release](https://github.com/Alanaktion/phproject/releases/latest) and extracting it over your existing installation.

If there is a change to the database needed after you've updated, you will see an alert in the Administration on your Phproject installation, and can update the database from there.

If something breaks after updating, clearing the APC cache or emptying the `tmp/cache/` directory will usually solve the problem.
