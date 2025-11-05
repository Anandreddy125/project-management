# syntax=docker/dockerfile:1

FROM node:16.17.0-bullseye-slim

WORKDIR /app

# Install system dependencies and PHP 8.1
RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
        ca-certificates apt-transport-https lsb-release curl wget gnupg2 unzip git && \
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/sury-php.list && \
    curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury.gpg && \
    apt-get update -y && \
    apt-get install -y --no-install-recommends \
        php8.1 php8.1-cli php8.1-common php8.1-curl php8.1-xml php8.1-zip php8.1-gd php8.1-mbstring php8.1-mysql && \
    php -v && \
    apt-get install -y composer && \
    rm -rf /var/lib/apt/lists/*

# Copy project files
COPY . .

# Copy environment file
COPY .env.example .env

# Install PHP and Node dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader && \
    npm install && \
    php artisan key:generate

# Expose app port (optional)
EXPOSE 8000

# Default command
CMD ["bash", "./run.sh"]
