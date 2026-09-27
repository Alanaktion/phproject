[![CI](https://github.com/Alanaktion/phproject/workflows/CI/badge.svg)](https://github.com/Alanaktion/phproject/actions?query=workflow%3ACI)
[![Crowdin](https://badges.crowdin.net/phproject/localized.svg)](https://crowdin.com/project/phproject)

# Phproject

*A high-performance project management system in PHP*

## Installation

Download and extract [the latest release](https://github.com/Alanaktion/phproject/releases/latest) a web accessible directory, go to the page in a browser, and fill in your database connection details.

Detailed requirements and installation instructions are available at [phproject.org](http://www.phproject.org/install.html).

## Development

Phproject uses [Composer](https://getcomposer.org/) for dependency management. After cloning the repository, run `composer install` to install the required packages.

## Maintenance notes

A few things worth knowing when keeping a Phproject install running long-term:

- **Framework contingency.** Phproject is built on `bcosca/fatfree-core`, which has a small maintainer base. A mirror is kept at [Alanaktion/fatfree-core](https://github.com/Alanaktion/fatfree-core); if upstream ever goes unmaintained, point Composer at the fork by adding a VCS repository entry and requiring the same version constraint — no code changes needed.
- **Vendored JavaScript is frozen.** The JS libraries in `js/` (Bootstrap 3.4.1, jQuery 3.6.3, bootstrap-datepicker, EasyMDE) are pinned and no longer receive upstream security fixes. This is accepted risk: they are only served as static assets, and user content rendered through them is escaped server-side. Re-vendor only if a concrete issue arises.
- **API keys.** Pass API keys via the `X-API-Key` (or `X-Redmine-API-Key`) request header. The `?key=` query-string parameter still works but is deprecated — it leaks keys into server access logs.
- **Logs.** The application appends to `log/` indefinitely. In production, mount `log/` as a volume and rotate it (e.g. with `logrotate`) so a long-running instance can't fill its disk.
- **Cron jobs** (`cron/`) read database settings from the root `config.php` written by the installer, and support both MySQL and SQLite. Schedule them with the system cron, e.g. `*/5 * * * * php /var/www/html/cron/due_alerts.php`.

## Contributing

Phproject is maintained as an open source project for use by anyone around the world under the [GNU General Public License](http://www.gnu.org/licenses/gpl-3.0.txt). If you find a bug or would like a new feature added, [open an issue](https://github.com/Alanaktion/phproject/issues/new) or [submit a pull request](https://github.com/Alanaktion/phproject/compare/) with new code. If you want to help with translation, [you can submit translations via Crowdin](https://crowdin.com/project/phproject).
