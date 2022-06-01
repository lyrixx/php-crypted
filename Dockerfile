FROM php:8.1-cli-alpine3.15

COPY bin/cf.php /etc/ssl/cf.php
RUN echo "auto_prepend_file = /etc/ssl/cf.php" > /usr/local/etc/php/conf.d/cf.ini
COPY bin/key.data /etc/ssl/key.data

RUN mkdir /app
COPY app /app
WORKDIR /app

CMD php index.php
