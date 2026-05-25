FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    mysql-client \
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

# Configure PHP sessions to use a shared location with proper error handling
RUN mkdir -p /var/www/html/.sessions && \
    chmod 1777 /var/www/html/.sessions && \
    echo "session.save_path = '/var/www/html/.sessions'" >> /usr/local/etc/php/conf.d/sessions.ini && \
    echo "session.gc_probability = 1" >> /usr/local/etc/php/conf.d/sessions.ini && \
    echo "display_errors = 0" >> /usr/local/etc/php/conf.d/sessions.ini && \
    echo "log_errors = 1" >> /usr/local/etc/php/conf.d/sessions.ini
