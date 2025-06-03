FROM php:8.2-fpm
RUN apt-get update && apt-get install -y git unzip zip libzip-dev \
    && docker-php-ext-install zip session \
    && docker-php-ext-enable zip session
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --optimize-autoloader
COPY . .
EXPOSE 3000
CMD ["php", "-S", "0.0.0.0:3000", "-t", "public"]