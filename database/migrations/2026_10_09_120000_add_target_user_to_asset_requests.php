<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_requests', 'target_user_id')) {
                $table->unsignedBigInteger('target_user_id')->nullable()->after('computer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            if (Schema::hasColumn('asset_requests', 'target_user_id')) {
                $table->dropColumn('target_user_id');
            }
        });
    }
};
