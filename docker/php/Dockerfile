
# PHP-FPM runtime for the Laravel application
FROM php:8.5-fpm

# Install system packages needed by PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo_pgsql \
    bcmath \
    pcntl \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer from the official Composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set the application working directory
WORKDIR /var/www

# Copy the Laravel project into the image
COPY . .

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist

# Ensure Laravel can write logs and cached files
RUN chown -R www-data:www-data storage bootstrap/cache

# PHP-FPM listens on port 9000 inside the container
EXPOSE 9000

CMD ["php-fpm"]