FROM php:8.2-apache

LABEL org.opencontainers.image.title="Cyberpunk Roller" \
      org.opencontainers.image.description="Cyberpunk 2020 combat calculator" \
      org.opencontainers.image.source="https://github.com/febrile42/cyberpunk-roller" \
      org.opencontainers.image.licenses="PolyForm-Noncommercial-1.0.0"

# Enable mod_rewrite (required for .htaccess RewriteRules)
RUN a2enmod rewrite

# Install APCu (pdo_sqlite is built into PHP — no separate install needed)
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# PHP production settings
RUN { \
    echo 'display_errors=Off'; \
    echo 'log_errors=On'; \
    echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/production.ini

# APCu settings
RUN { \
    echo 'apc.enabled=1'; \
    echo 'apc.enable_cli=0'; \
    echo 'apc.shm_size=32M'; \
    } > /usr/local/etc/php/conf.d/apcu.ini

# Allow .htaccess to override Apache settings (AllowOverride None by default)
RUN printf '<VirtualHost *:80>\n\tDocumentRoot /var/www/html\n\t<Directory /var/www/html>\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>\n</VirtualHost>\n' \
    > /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

RUN mkdir -p /var/lib/cyberpunk-roller && chown www-data:www-data /var/lib/cyberpunk-roller

RUN chown -R www-data:www-data /var/www/html
