
FROM php:8.2-apache

# Install ekstensi PHP untuk koneksi MySQL
RUN docker-php-ext-install mysqli

# Aktifkan Apache mod_rewrite
RUN a2enmod rewrite

# Direktori kerja aplikasi
WORKDIR /var/www/html

# Salin source code aplikasi
COPY . /var/www/html

# Berikan hak tulis Apache hanya pada folder uploads
RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80
