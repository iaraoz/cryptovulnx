FROM php:8.1-apache

# Extensiones PHP necesarias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# mod_rewrite + headers + Indexes (intencional para uploads/, backup/)
RUN a2enmod rewrite headers

# Tools dentro del contenedor (utiles para LABs de RCE/LFI)
RUN apt-get update && apt-get install -y \
    curl \
    wget \
    && rm -rf /var/lib/apt/lists/*

# Permitir .htaccess override en docroot
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Options +Indexes +FollowSymLinks\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/cryptovulnx.conf \
    && a2enconf cryptovulnx

# Copiar TODO el proyecto al docroot.
# Las exclusiones se manejan con .dockerignore
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads /var/www/html/backup 2>/dev/null || true

EXPOSE 80

CMD ["apache2-foreground"]
