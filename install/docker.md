---
layout: md
title: Install with Docker
---
<h1 class="page-header">Install with Docker</h1>

If you'd prefer to run your Phproject instance in a Docker container, this is fairly simple to do with the our optimized PHP image.

You should have some familiarity with Docker and ideally PHP and nginx before trying this setup, but it's fairly straightforward.

## Single container setup

If you already have a database and web server, you can just run a single Docker container with PHP-FPM.

Start by extracting the [latest release](https://github.com/Alanaktion/phproject/releases/latest) to the `/var/www/phproject` directory, then start a container:

```bash
docker run -d \
  -v /var/www/phproject:/var/www/phproject
  -p 127.0.0.1:9000:9000/tcp \
  --name phproject \
  alanaktion/phproject
```

From there, just configure your web server to connect to the FastCGI server on port 9000, and your site should work. Visit the site in a browser to complete the setup.

## Complete environment

If you need a full environment, including a database and web server, that is doable as well. We'll be using [`docker-compose`](https://github.com/docker/compose) for our examples, but it should be similar with other setups. Kubernetes _may_ work but Phproject is designed to use a centralized filesystem, so you'd need to get creative with asset storage volumes.

Start by extracting the [latest release](https://github.com/Alanaktion/phproject/releases/latest) to the `/var/www/phproject` directory, then create a `docker-compose.yml` file:

```yml
version: '3.1'

services:

  phproject:
    image: alanaktion/phproject:latest
    restart: always
    volumes:
      - /var/www/phproject:/var/www/phproject

  db:
    image: mysql:8.0
    restart: always
    environment:
      MYSQL_DATABASE: phproject
      MYSQL_USER: phproject
      MYSQL_PASSWORD: secret
      MYSQL_RANDOM_ROOT_PASSWORD: '1'
    volumes:
      - db:/var/lib/mysql

  nginx:
    image: nginx:latest
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
      - /var/www/phproject:/var/www/phproject
    ports:
      - 80:80

volumes:
  db:
```

You'll also need an `nginx.conf` file:

```nginx
http {
  server {
    listen 80;

    server_name phproject.example.com;
    root /var/www/phproject;
    index index.php;

    location / {
      try_files $uri $uri/ /index.php?$args;
    }

    location ~ [^/]\.php(/|$) {
      fastcgi_split_path_info ^(.+\.php)(/.+)$;
      fastcgi_pass phproject:9000;
      include fastcgi_params;
      fastcgi_param PATH_INFO $fastcgi_path_info;
    }
  }
}
```

Then, just run `docker-compose up` to start your containers. Once started, you can use the web interface to complete the Phproject installation. Use `db` as your database host if you're using the example `docker-compose.yml` from above.
