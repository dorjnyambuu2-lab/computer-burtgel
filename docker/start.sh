#!/bin/sh
# Docker контейнер асах үед: тохиргоог кэшлэж, өгөгдлийн сангийн бүтцийг шинэчилж, Apache-г асаана.
set -e
cd /var/www/html

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Шинэ миграци байвал л ажиллана (байгаа өгөгдлийг устгахгүй)
php artisan migrate --force

# Супер админ: байхгүй бол SUPERADMIN_DEFAULT_PASSWORD-оор үүсгэнэ, байгаа бол хөндөхгүй.
# APP_ENV=production үед жишээ admin, employee хэрэглэгч үүсэхгүй.
php artisan db:seed --class=UserSeeder --force

exec apache2-foreground
