FROM php:8.2-apache

# Gerekli PHP eklentilerini yükle (isteğe bağlı)
RUN docker-php-ext-install pdo_mysql

# Apache'nin index dosyası olarak PHP'yi tanıması için
COPY . /var/www/html/

# Apache'yi etkinleştir
EXPOSE 80