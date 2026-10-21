<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Анхны хэрэглэгчид. Аль хэдийн байгаа бол нууц үгийг нь дарж бичихгүй
     * (дахин seed хийхэд солисон нууц үг буцаж анхны болохгүй).
     */
    public function run(): void
    {
        // Супер админ -> нэвтрэх нэр: superadmin, анхны нууц үг: SUPERADMIN_DEFAULT_PASSWORD
        // Анхны нууц үгээр нэвтэрмэгц заавал солиулна.
        User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Супер админ',
                'email' => 'admin@example.com',
                'password' => config('security.superadmin_default_password'),
                'role' => 'superadmin',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );

        // Жишээ админ, ажилтан (зөвхөн хөгжүүлэлтийн орчинд)
        if (! app()->isProduction()) {
            User::firstOrCreate(
                ['username' => 'admin'],
                ['name' => 'Админ', 'email' => 'manager@example.com', 'password' => 'admin12345', 'role' => 'admin', 'is_active' => true]
            );

            User::firstOrCreate(
                ['username' => 'employee'],
                ['name' => 'Ажилтан', 'email' => 'employee@example.com', 'password' => 'employee12345', 'role' => 'employee', 'is_active' => true]
            );
        }
    }
}
