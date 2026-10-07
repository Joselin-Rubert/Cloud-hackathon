FROM php:8.2-apache-bookworm

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y --no-install-recommends \
        mariadb-server mariadb-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/assets

ENV SF_DB_HOST=127.0.0.1
ENV SF_DB_NAME=studentflow
ENV SF_DB_USER=studentflow
ENV SF_DB_PASS=studentflow

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]