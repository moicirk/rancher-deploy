FROM php:5.6

RUN apt-get update && apt-get install -y git

COPY . /app
WORKDIR /app

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install