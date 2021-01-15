# Based on https://github.com/php-actions/php-build/blob/5ca52623f4f06888eaf5d6593b0aa4818f624e6d/Dockerfile
FROM ubuntu:latest

# Prepare php-build
RUN apt-get update --fix-missing
RUN apt-get install -y libkrb5-dev libc-client-dev git
RUN git clone git://github.com/php-build/php-build /tmp/php-build
WORKDIR /tmp/php-build
RUN ./install-dependencies.sh
RUN ./install.sh
ENV PHP_BUILD_CONFIGURE_OPTS="--with-libxml --with-curl --with-zip --with-mysqli --with-pdo-mysql --enable-bcmath --enable-gd --enable-intl --enable-mbstring"

# Build PHP versions
RUN php-build 8.0.1 /etc/php/8.0
RUN php-build 7.4.14 /etc/php/7.4
RUN php-build 7.3.26 /etc/php/7.3
RUN php-build 7.2.33 /etc/php/7.2
RUN php-build 7.1.33 /etc/php/7.1

# Set default version
COPY switch-php-version /usr/local/bin/.
RUN switch-php-version 8.0

# Install composer
RUN curl -o /usr/local/bin/composer -L https://getcomposer.org/composer-stable.phar
RUN chmod +x /usr/local/bin/composer

# Install node+npm
RUN curl -sL https://deb.nodesource.com/setup_14.x | bash -
RUN apt-get install -y nodejs
