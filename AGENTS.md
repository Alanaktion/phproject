# AGENTS.md - Working with Phproject

This guide is designed for AI coding agents and developers working on the Phproject repository.

## Project Overview

Phproject is a high-performance project management system written in PHP. It's a full-featured web application built on the Fat-Free Framework (F3) with support for MySQL and SQLite databases.

**Key Technologies:**
- PHP 8.1+ (PSR-12 coding standard)
- Fat-Free Framework (F3) v3.9+
- MySQL 8+ or SQLite
- Composer for dependency management
- Docker support included

## Repository Structure

```
/home/runner/work/phproject/phproject/
├── app/              # Core application code (models, controllers, views)
│   ├── controller/   # Application controllers
│   ├── model/        # Database models
│   ├── view/         # Template views
│   ├── helper/       # Helper classes
│   ├── dict/         # Language files
│   └── plugin/       # Plugin directory
├── tests/            # PHPUnit test files
├── css/              # Stylesheets
├── js/               # JavaScript files
├── img/              # Images and assets
├── uploads/          # User-uploaded files
├── tmp/              # Temporary files
├── db/               # Database migrations
├── cron/             # Cron job scripts
├── composer.json     # PHP dependencies
├── .phpcs.xml        # PHP CodeSniffer configuration
├── phpunit.xml       # PHPUnit configuration
└── rector.php        # Rector refactoring tool configuration
```

## Initial Setup

### Prerequisites
- PHP 8.1 or higher
- Composer
- MySQL 8+ or SQLite
- Git

### Installation Steps

1. **Clone the repository:**
   ```bash
   cd /home/runner/work/phproject/phproject
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Install Phproject (for testing):**
   
   For MySQL:
   ```bash
   php install.php --site-name=Test --site-url=http://localhost/ \
     --timezone=America/Phoenix --admin-username=test \
     --admin-email=test@example.com --admin-password=secret \
     --db-host=127.0.0.1 --db-port=3306 --db-user=root
   ```
   
   For SQLite:
   ```bash
   php install.php --site-name=Test --site-url=http://localhost/ \
     --timezone=America/Phoenix --admin-username=test \
     --admin-email=test@example.com --admin-password=secret \
     --db-engine=sqlite --db-name=database.sqlite
   ```

## Development Workflow

### Code Quality Tools

The project uses several tools to maintain code quality:

#### 1. PHP CodeSniffer (PSR-12 Standard)
Checks code style compliance with modified PSR-12 standard.

```bash
vendor/bin/phpcs
```

**Configuration:** `.phpcs.xml`
- Scans `app/` directory
- Modified PSR-12 standard with backwards compatibility exceptions
- Allows warnings in CI
- Bans certain functions (sizeof, delete, print, is_null, create_function)

#### 2. Rector (Code Modernization)
Automated refactoring and code modernization tool.

```bash
vendor/bin/rector process --dry-run
```

**Configuration:** `rector.php`
- Targets PHP 8.1+ features
- Scans: `app/`, `cron/`, `tests/`
- Skips: `app/plugin/`
- Rules: type declarations, dead code removal, code quality, early returns, coding style

#### 3. Syntax Check
Basic PHP syntax validation:

```bash
find . -name "*.php" ! -path "./vendor/*" -exec php -l {} 2>&1 \; | grep "syntax error"
```

### Testing

#### Running Tests
```bash
vendor/bin/phpunit
```

Or with output colors and no progress:
```bash
vendor/bin/phpunit --no-progress
```

**Test Structure:**
- Bootstrap: `tests/bootstrap.php`
- Test files: `tests/*Test.php`
- Current tests: API, plugins, string helpers

**Configuration:** `phpunit.xml`

### Building & Deployment

#### Docker
The project includes Docker support:

```bash
docker build -t phproject .
```

Production build (no dev dependencies):
```bash
docker run --rm --volume $PWD:/app --user $(id -u):$(id -g) \
  composer install --no-ansi --no-interaction --no-dev
```

#### Multi-platform Docker build:
```bash
docker buildx build --platform "linux/amd64,linux/arm/v7,linux/arm64" \
  -t phproject:latest .
```

## Common Tasks

### Making Code Changes

1. **Before making changes:**
   - Run syntax check
   - Run PHP CodeSniffer: `vendor/bin/phpcs`
   - Run existing tests: `vendor/bin/phpunit`
   - Note any pre-existing issues (not your responsibility to fix)

2. **After making changes:**
   - Run PHP CodeSniffer on changed files
   - Run relevant tests
   - Ensure changes follow PSR-12 standard

3. **Code style guidelines:**
   - Follow PSR-12 coding standard (with project exceptions)
   - Use type declarations where possible (PHP 8.1+)
   - Avoid banned functions (sizeof, is_null, print, delete, create_function)
   - Match existing comment style in the file

### Adding Dependencies

When adding new Composer packages:

```bash
composer require vendor/package
```

For development-only dependencies:

```bash
composer require --dev vendor/package
```

### Plugin Development

Plugins should be placed in `app/plugin/`. Each plugin must:

1. Have a `Base` class in `base.php` extending `\Plugin`
2. Implement `_load()` method for initialization
3. Implement `_installed()` method to check installation status
4. Optionally implement `_install()` method for installation
5. Include PHPDoc comment block with `@package` and `@author` tags
6. May include a `dict/` directory for localization

See `app/plugin/README.md` for full plugin standards.

### Database Work

- Database schema/migrations are in `db/` directory
- The application supports both MySQL and SQLite
- Test with both database engines when making schema changes

## Continuous Integration

The project uses GitHub Actions for CI (`.github/workflows/ci.yml`):

**CI Pipeline:**
1. Syntax check (PHP lint)
2. Composer install
3. PHP CodeSniffer
4. Install Phproject (both MySQL and SQLite)
5. PHPUnit tests

**Matrix Testing:**
- PHP versions: 8.1, 8.2, 8.3, 8.4
- Database engines: MySQL 8, SQLite

## Contributing

### Pull Request Guidelines

1. **New translations:** Submit via [Crowdin](https://crowdin.com/project/phproject) instead of PRs
2. **Code style:** Follow PSR-12 standard (see `.phpcs.xml` for exceptions)
3. **New features:** Consider implementing as a plugin rather than core changes
4. **Testing:** Run all tests before submitting
5. **Documentation:** Update relevant docs for significant changes

See `CONTRIBUTING.md` for full guidelines.

### Security

Report security vulnerabilities via:
- [huntr.dev](https://huntr.dev/bounties/disclose)
- Email: alan@phpizza.com (PGP: [keybase.io/alanaktion](https://keybase.io/alanaktion/pgp_keys.asc))

See `SECURITY.md` for full security policy.

## Troubleshooting

### Common Issues

**Composer install fails:**
- Ensure PHP 8.1+ is installed and active
- Check PHP extensions (PDO, pdo_mysql/pdo_sqlite, etc.)

**Tests fail:**
- Ensure Phproject is installed (run `install.php`)
- Check database connection settings
- Review `tests/bootstrap.php` for test setup

**PHPCS fails on legacy code:**
- Project allows some PSR-12 exceptions for backwards compatibility
- Focus on new code following standards
- See `.phpcs.xml` for allowed exceptions

**Docker build issues:**
- Ensure Docker and Docker Buildx are installed
- Check platform support with `docker buildx ls`

## Additional Resources

- **Website:** [phproject.org](http://www.phproject.org/)
- **Installation Guide:** [phproject.org/install.html](http://www.phproject.org/install.html)
- **Plugin Guide:** [phproject.org/plugins.html](https://www.phproject.org/plugins.html)
- **GitHub Issues:** [github.com/Alanaktion/phproject/issues](https://github.com/Alanaktion/phproject/issues)
- **Crowdin Translations:** [crowdin.com/project/phproject](https://crowdin.com/project/phproject)

## Quick Reference Commands

```bash
# Install dependencies
composer install

# Code quality checks
vendor/bin/phpcs                          # Check code style
vendor/bin/rector process --dry-run       # Check for refactoring suggestions
find . -name "*.php" ! -path "./vendor/*" -exec php -l {} \;  # Syntax check

# Testing
vendor/bin/phpunit                        # Run all tests
vendor/bin/phpunit --no-progress          # Run tests without progress output

# Install application
php install.php [options]                 # Interactive or CLI install

# Docker
docker build -t phproject .               # Build Docker image
docker run -p 8080:80 phproject           # Run container
```
