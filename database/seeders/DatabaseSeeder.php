<?php

namespace Database\Seeders;

use App\Models\Computer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Супер админ (анхны нууц үгтэй) болон жишээ хэрэглэгчид
        $this->call(UserSeeder::class);

        // Жишээ өгөгдөл (зөвхөн хоосон үед)
        if (Computer::count() === 0) {
            Computer::factory()->count(25)->create();
        }
    }
}
