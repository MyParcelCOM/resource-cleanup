FROM php:8.2-cli-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        curl \
        sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory "/app"

WORKDIR /app

# Install dependencies first (cached layer unless composer.json changes)
COPY composer.json .

# Copy the rest of the source
COPY . .

# Finish autoloader now that all source files are present
RUN composer dump-autoload --optimize

CMD ["vendor/bin/phpunit"]
