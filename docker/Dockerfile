FROM 192.168.1.40:5001/library/wordpress:6.5-php8.2-apache

RUN curl -fL --retry 5 --connect-timeout 15 --max-time 120 \
    https://pecl.php.net/get/redis-6.3.0.tgz -o /tmp/redis.tgz \
    && pecl install /tmp/redis.tgz \
    && docker-php-ext-enable redis \
    && rm -f /tmp/redis.tgz
