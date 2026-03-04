FROM php:8.2-apache

# Enable mod_rewrite (required for .htaccess RewriteRules)
RUN a2enmod rewrite

# Install pdo_mysql and APCu
RUN docker-php-ext-install pdo_mysql \
    && pecl install apcu \
    && docker-php-ext-enable apcu

# Allow .htaccess to override Apache settings (AllowOverride None by default)
RUN printf '<VirtualHost *:80>\n\tDocumentRoot /var/www/html\n\t<Directory /var/www/html>\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>\n</VirtualHost>\n' \
    > /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html
