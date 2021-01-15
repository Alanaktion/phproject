# phproject-docker

This Docker image used in automated testing for Phproject. It is a general-purpose PHP image with versions 7.1-8.0 available, bundled with the latest Node.js LTS.

## Building

To build and publish the container, use something similar to this:

```bash
docker build -t alanaktion/phproject-ci:latest .
docker push alanaktion/phproject-ci:latest
```

## Usage

This image works wherever, but it's best with GitHub Actions. Here's a simple example workflow:

```yaml
name: Example
on: [push, pull_request]
jobs:
  ci:
    runs-on: ubuntu-latest
    container:
      image: alanaktion/phproject-ci

    strategy:
      matrix:
        php: [7.2, 7.3, 7.4, 8.0]

    steps:
    - uses: actions/checkout@v2
    - run: switch-php-version ${{ matrix.php }}
    - run: composer install --no-ansi --no-interaction
    - run: vendor/bin/phpunit
```
