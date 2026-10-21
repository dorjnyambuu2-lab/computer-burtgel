<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action');
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('subject_label')->nullable();
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->nullable();
            });
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'subject_label')) {
                $table->string('subject_label')->nullable();
            }
            if (! Schema::hasColumn('activity_logs', 'changes')) {
                $table->json('changes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            foreach (['subject_label', 'changes'] as $c) {
                if (Schema::hasColumn('activity_logs', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};