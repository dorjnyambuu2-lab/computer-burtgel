<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('computers', 'acknowledged_at')) {
            Schema::table('computers', function (Blueprint $table) {
                $table->timestamp('acknowledged_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Багана анхнаасаа байсан тул энд устгахгүй.
    }
};