<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_requests')) {
            Schema::create('asset_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('computer_id')->nullable();
                $table->string('type', 20);                  // issue | request
                $table->string('subject')->nullable();
                $table->text('description');
                $table->string('status', 20)->default('pending'); // pending | in_progress | done | rejected
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('computers', 'acknowledged_at')) {
            Schema::table('computers', function (Blueprint $table) {
                $table->timestamp('acknowledged_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_requests');
        if (Schema::hasColumn('computers', 'acknowledged_at')) {
            Schema::table('computers', fn (Blueprint $t) => $t->dropColumn('acknowledged_at'));
        }
    }
};