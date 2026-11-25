FROM php:8.5-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install -j"$(nproc)" fileinfo mbstring pdo_mysql \
    && a2enmod rewrite \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/campus2career.conf \
    && a2enconf campus2career \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY railway-start.sh /usr/local/bin/railway-start
RUN chmod +x /usr/local/bin/railway-start

EXPOSE 8080
CMD ["/usr/local/bin/railway-start"]
