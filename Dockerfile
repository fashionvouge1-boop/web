# StyleHub — PHP 8.2 + Apache image for Render.com
FROM php:8.2-apache

# --- PHP extensions the app uses ---
# pdo_mysql (DB), curl (URL import), dom/xml (URL import scraper), mbstring, fileinfo
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-install pdo_mysql curl mbstring dom fileinfo \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# --- Allow .htaccess overrides (the app relies on .htaccess rules) ---
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/sites-available/000-default.conf

# --- Copy the application ---
WORKDIR /var/www/html
COPY . /var/www/html/

# --- Make the uploads folder writable by Apache ---
RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# --- Listen on Render's PORT (defaults to 10000) ---
EXPOSE 10000
CMD sed -ri "s/^Listen 80$/Listen ${PORT:-10000}/" /etc/apache2/ports.conf \
 && sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground
