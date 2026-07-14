FROM php:8.2-apache

# Install required dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    mariadb-client \
    zlib1g-dev \
    libxml2-dev \
    && docker-php-ext-install \
    intl \
    pdo_mysql \
    zip \
    gettext \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module (required by Symfony)
RUN a2enmod rewrite

# Copy virtual host config (defined below)
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

# Set PHP default timezone
RUN echo "date.timezone=Europe/Madrid" > /usr/local/etc/php/conf.d/timezone.ini

# Match host user permissions (optional, for file permission compatibility)
ARG HOST_UID=1000
ARG HOST_GID=1000

RUN if ! getent group ${HOST_GID}; then \
      groupmod -g ${HOST_GID} www-data; \
    fi && \
    usermod -u ${HOST_UID} -g www-data www-data

# Create Symfony var/ directory and set permissions
RUN mkdir -p /var/www/html/var && \
    chown -R www-data:www-data /var/www/html
