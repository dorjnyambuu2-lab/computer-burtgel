# Render (болон бусад Docker орчин)-д зориулсан image. XAMPP дээрх ажиллагаанд нөлөөгүй.
# 1) PHP сангууд
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# 2) Интерфейсийн файлууд (Vite + Tailwind). Pagination-ий Blade загварт байгаа классууд CSS-д орохын тулд vendor хэрэгтэй
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY scripts ./scripts
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN mkdir -p storage/framework/views public && npm run build

# 3) Ажиллах орчин: PHP 8.2 + Apache
FROM php:8.2-apache
# mod_deflate унтраана: CSS, JS-ийг урьдчилан шахсан .gz-ээр, HTML-ийг аппын CompressResponse-оор шахдаг тул
# дахин шахвал хөтөч CSS, JS-ийг уншиж чадахгүй болдог
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql pdo_mysql zip opcache \
    && a2enmod rewrite headers \
    && a2dismod -f deflate \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
# Render нь PORT хувьсагчаар портоо өгдөг (анхдагч 10000)
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf
ENV PORT=10000

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

EXPOSE 10000
CMD ["/var/www/html/docker/start.sh"]
