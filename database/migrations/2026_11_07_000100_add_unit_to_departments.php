<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Алба нэгжид харьяалагдана (заавал биш): Салбар → Нэгж → Алба. Нэгж устгавал алба нэгжгүй болно. */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $t) {
            $t->foreignId('unit_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', fn (Blueprint $t) => $t->dropConstrainedForeignId('unit_id'));
    }
};
