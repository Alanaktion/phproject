---
layout: md
title: Install with Docker
---
<h1 class="page-header">Install with Docker</h1>

## Quick Start: All-in-One Apache Container

The easiest way to get started with Phproject is to use the all-in-one Apache container, which includes PHP, Apache, and the Phproject code pre-installed. Perfect for quick tests, demos, or small deployments, but works well in production too.

### Single Container with SQLite

Phproject supports SQLite out of the box, so you can run everything in a single container with minimal setup:

```bash
docker run -d \
  -p 8080:80 \
  --name phproject \
  alanaktion/phproject:apache
```

Then visit `http://localhost:8080` in your browser to complete the setup. Select SQLite as the database, it will be stored inside the container. You can also use the [CLI installation](/install.html#command-line-installation) via `docker exec` if preferred.

**Persistence:**
To keep your data between container restarts or upgrades, mount the following volumes:

- `/var/www/html/uploads` — file uploads
- `/var/www/html/config.php` — site configuration (including database connection, site URL, etc.)
- `/var/www/html/app/plugins` — (optional) user plugins
- Any SQLite database file you use during setup

Example:

```bash
docker run -d \
  -p 8080:80 \
  -v ./uploads:/var/www/html/uploads \
  -v ./config.php:/var/www/html/config.php \
  -v ./plugins:/var/www/html/app/plugins \
  -v ./database.sqlite:/var/www/html/database.sqlite \
  --name phproject \
  alanaktion/phproject:apache
```

### Docker Compose with MySQL

For a more robust setup, you can use Docker Compose to run Phproject with a MySQL database. Be sure to mount the persistence volumes as shown below:

```yml
services:
  phproject:
    image: alanaktion/phproject:apache
    restart: always
    ports:
      - 8080:80
    volumes:
      - ./uploads:/var/www/html/uploads
      - ./config.php:/var/www/html/config.php
      - ./plugins:/var/www/html/app/plugins
    depends_on:
      - db
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
volumes:
  db:
```

After running `docker-compose up`, visit `http://localhost:8080` and use `db` as the database host during setup.

### Using a Reverse Proxy for HTTPS

For production deployments, it is recommended to use a reverse proxy (such as Nginx or Traefik) in front of the container to handle HTTPS. The container exposes port 80 by default. Configure your reverse proxy to forward HTTPS traffic to the container's port 80. See your proxy's documentation for details.

**Persistence Reminder:**
Always mount the persistence volumes (`uploads`, `config.php`, and optionally `app/plugins`) to avoid data loss during upgrades or container recreation.

### Deploying to Kubernetes

Phproject's Apache container can be deployed to Kubernetes, but you must ensure persistent storage for uploads, configuration, and (optionally) plugins. The recommended approach is to use PersistentVolumeClaims (PVCs) for each of the following paths:

- `/var/www/html/uploads` — file uploads
- `/var/www/html/config.php` — site configuration (including database connection, site URL, etc.)
- `/var/www/html/app/plugins` — (optional) user plugins

A basic example of a `volumeMounts` section in your Pod or Deployment spec:

```yaml
volumeMounts:
  - name: uploads
    mountPath: /var/www/html/uploads
  - name: config
    mountPath: /var/www/html/config.php
    subPath: config.php
  - name: plugins
    mountPath: /var/www/html/app/plugins
```

And the corresponding `volumes` section:

```yaml
volumes:
  - name: uploads
    persistentVolumeClaim:
      claimName: phproject-uploads
  - name: config
    persistentVolumeClaim:
      claimName: phproject-config
  - name: plugins
    persistentVolumeClaim:
      claimName: phproject-plugins
```

> **Note:** Each PersistentVolumeClaim (`claimName`) must be created in your cluster before deploying the container. This ensures your data, configuration, and plugins persist across pod restarts and upgrades.

For production, you should also use a Kubernetes Ingress or Service to expose the container, and a reverse proxy or Ingress controller (such as Nginx Ingress or Traefik) to handle HTTPS.

---

## Classic Container (PHP-FPM Only)

> **Note:** The classic container (`alanaktion/phproject:latest`) is primarily intended for development and advanced use cases. It requires a local copy of the Phproject codebase on the host machine, which is mounted into the container. This setup is not recommended for most production deployments.

You should have good familiarity with Docker and ideally PHP and nginx before trying this setup, but it's fairly straightforward.

### Single container setup

If you already have a database and web server, you can just run a single Docker container with PHP-FPM.

Start by extracting the [latest release](https://github.com/Alanaktion/phproject/releases/latest) to the `/var/www/phproject` directory on your host, then start a container:

```bash
docker run -d \
  -v /var/www/phproject:/var/www/phproject \
  -p 127.0.0.1:9000:9000/tcp \
  --name phproject \
  alanaktion/phproject
```

From there, just configure your web server to connect to the FastCGI server on localhost port 9000, and your site should work. Visit the site in a browser to complete the setup. External MySQL connections may require additional networking configuration on the container to access the host system, but SQLite can be used to avoid this added complexity.

### Complete environment

If you need a full environment, including a database and web server, that is doable as well. We'll be using [`docker-compose`](https://github.com/docker/compose) for our examples, but it should be similar with other setups.

Start by extracting the [latest release](https://github.com/Alanaktion/phproject/releases/latest) to the `/var/www/phproject` directory on your host, then create a `docker-compose.yml` file:

```yml
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

Then, just run `docker-compose up` to start your containers. Once started, you can use the web interface to complete the Phproject installation. Use `db` as your MySQL database host if you're using the example `docker-compose.yml` from above.
