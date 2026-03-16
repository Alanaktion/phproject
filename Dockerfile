FROM docker.io/library/php:8.4-apache

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions exif gd zip pdo_mysql intl

RUN a2enmod rewrite

COPY --chown=33:33 . /var/www/html/
RUN echo 'upload_max_filesize = 1024M' > /usr/local/etc/php/conf.d/limits.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
