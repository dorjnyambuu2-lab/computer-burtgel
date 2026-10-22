<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Хэрэглэгч одоо системд байгаа (онлайн) эсэхийг харуулах */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
            $table->boolean('is_online')->default(false)->after('last_seen_at');
        });

        DB::table('users')->update(['last_seen_at' => DB::raw('last_login_at')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_seen_at', 'is_online']);
        });
    }
};
