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
# Жишээ өгөгдөл: SEED_DEMO=1 бөгөөд төхөөрөмж огт бүртгэлгүй үед л (DEMO_PASSWORD заавал). Алдаа гарвал сайт ажилласаар байна
if [ "$SEED_DEMO" = "1" ]; then
    php artisan db:seed --class=DemoSeeder --force || echo "DemoSeeder: жишээ өгөгдөл оруулж чадсангүй" >&2
fi

exec apache2-foreground
