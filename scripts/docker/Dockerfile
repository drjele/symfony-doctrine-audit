FROM php:8.1.3-fpm-alpine

ENV ENV="/root/.profile"

RUN apk add --update shadow && usermod -u 1000 www-data

RUN apk add --update \
    git \
    openssh

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/*

COPY id_* /root/.ssh/
RUN chmod 600 /root/.ssh/id_*
COPY .profile* /root/

RUN ssh-keyscan gitlab.com >> ~/.ssh/known_hosts

RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

WORKDIR /var/www/app/

CMD ["php-fpm", "-F"]

EXPOSE 9001
