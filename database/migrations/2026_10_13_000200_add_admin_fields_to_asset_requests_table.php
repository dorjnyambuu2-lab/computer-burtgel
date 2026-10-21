<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Байхгүй багануудыг л нэмнэ (байгаа бол өөрчлөхгүй). */
    public function up(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_requests', 'admin_note')) {
                $table->string('admin_note', 500)->nullable();
            }

            if (! Schema::hasColumn('asset_requests', 'handled_by')) {
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            if (Schema::hasColumn('asset_requests', 'handled_by')) {
                $table->dropConstrainedForeignId('handled_by');
            }

            if (Schema::hasColumn('asset_requests', 'admin_note')) {
                $table->dropColumn('admin_note');
            }
        });
    }
};