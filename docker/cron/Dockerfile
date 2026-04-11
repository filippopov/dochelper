FROM php:8.3-cli

# Install system dependencies (same as backend)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy crontab
COPY docker/cron/crontab /etc/cron.d/gold-rush-cron
RUN chmod 0644 /etc/cron.d/gold-rush-cron \
    && touch /var/log/cron.log

WORKDIR /var/www/backend

# Run cron in foreground
CMD ["cron", "-f"]
