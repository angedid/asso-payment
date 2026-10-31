FROM php:8.4-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pgsql \
    sockets \
    zip \
    pcntl \
    bcmath \
    intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Ensure storage and bootstrap directories exist with proper permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && (chmod 600 storage/oauth-*.key 2>/dev/null || true) \
    && (test -f .env || cp .env.example .env)

# Ensure runtime root symlinks for pem keys and configs
RUN ln -sf public/payment-private.pem payment-private.pem \
    && ln -sf public/payment-public.pem payment-public.pem \
    && ln -sf app/Jobs/global-var-config.ini global-var-config.ini

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Expose port
EXPOSE 8000

# Default command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
