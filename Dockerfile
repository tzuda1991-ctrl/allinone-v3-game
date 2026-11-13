FROM php:7.4-apache

RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql gd zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf /etc/apache2/sites-available/*.conf \
    && printf "display_errors = Off\nerror_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED\n" > /usr/local/etc/php/conf.d/ci3-clean.ini

WORKDIR /var/www/html
COPY . /var/www/html/
COPY railway-entrypoint.sh /usr/local/bin/railway-entrypoint.sh

RUN mkdir -p /var/www/html/data/post /var/www/html/data/Redeem /var/www/html/data/cards /var/www/html/application/logs /var/www/html/application/cache \
    && chmod -R 777 /var/www/html/data /var/www/html/application/logs /var/www/html/application/cache \
    && chmod +x /usr/local/bin/railway-entrypoint.sh

EXPOSE 8080
CMD ["/usr/local/bin/railway-entrypoint.sh"]
