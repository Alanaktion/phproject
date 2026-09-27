FROM docker.io/library/php:8.4-apache

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions exif gd zip pdo_mysql pdo_sqlite intl imap

RUN a2enmod rewrite

# Container health check (Apache on :80)
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s \
    CMD php -r "exit(@fsockopen('127.0.0.1', 80) ? 0 : 1);"

COPY --chown=33:33 . /var/www/html/
RUN printf 'upload_max_filesize = 1024M\npost_max_size = 1024M\n' > /usr/local/etc/php/conf.d/limits.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
