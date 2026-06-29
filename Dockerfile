# Neon/Retro Casino — production image (Apache + PHP 8.2)
FROM php:8.2-apache

# SQLite PDO driver + Apache modules the app relies on
RUN apt-get update \
 && apt-get install -y --no-install-recommends libsqlite3-dev \
 && docker-php-ext-install pdo_sqlite \
 && a2enmod rewrite headers expires

# Serve app/public as the web root
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Application code (the pure engine + the app)
COPY . /var/www/html/

# Writable data dir for the SQLite database and error log
RUN mkdir -p /var/www/html/app/data \
 && chown -R www-data:www-data /var/www/html/app/data

EXPOSE 80
