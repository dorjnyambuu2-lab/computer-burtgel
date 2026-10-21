<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_requests', 'admin_note')) {
                $table->text('admin_note')->nullable();
            }
            if (! Schema::hasColumn('asset_requests', 'handled_by')) {
                $table->unsignedBigInteger('handled_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            foreach (['admin_note', 'handled_by'] as $c) {
                if (Schema::hasColumn('asset_requests', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};