FROM php:8.0.9-fpm-alpine

ENV ENV="/root/.profile"

RUN apk add --update shadow && usermod -u 1000 www-data

RUN apk add --update \
    composer \
    git \
    openssh

RUN rm -rf /var/cache/apk/* && rm -rf /tmp/*

COPY id_* /root/.ssh/
RUN chmod 600 /root/.ssh/id_*
COPY .profile* /root/

RUN ssh-keyscan gitlab.com >> ~/.ssh/known_hosts
RUN cp $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

CMD ["php-fpm", "-F"]

WORKDIR /var/www/app/

EXPOSE 9001
