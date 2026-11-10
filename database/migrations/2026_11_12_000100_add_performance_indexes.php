<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Хурдны индексүүд. Хүснэгт томрох тусам удаашрахаас сэргийлнэ:
 *  - activity_logs: нэвтрэх, гарах бүрд өсдөг. Огноо, үйлдэл, объектоор шүүдэг (Админы түүх нь subject_type+subject_id-аар).
 *  - asset_requests.status, computer_transfers.status: хажуугийн цэсний «хүлээгдэж буй» тоо хуудас бүрт тоологдоно.
 *  - computers.deleted_at: soft delete — бараг бүх асуулга «deleted_at IS NULL» нөхцөлтэй.
 *  - computers.warranty_until: баталгаа дуусах сануулга.
 */
return new class extends Migration
{
    private const INDEXES = [
        'activity_logs' => [['created_at'], ['action'], ['subject_type', 'subject_id']],
        'asset_requests' => [['status']],
        'computer_transfers' => [['status']],
        'computers' => [['deleted_at'], ['warranty_until']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $columns) {
                if (array_diff($columns, Schema::getColumnListing($table)) || Schema::hasIndex($table, $columns)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $t) => $t->index($columns));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                $name = strtolower($table.'_'.implode('_', $columns).'_index');
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
                }
            }
        }
    }
};
