# Dockerfile
FROM php:8.2-apache

# تثبيت PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql mysqli

# نسخ التطبيق إلى مجلد Apache
COPY app.php /var/www/html/

# إعطاء الصلاحيات المناسبة
RUN chown -R www-data:www-data /var/www/html/
