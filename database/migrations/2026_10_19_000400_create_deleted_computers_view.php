<?php

use App\Enums\EquipmentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `deleted_computers` view: зөвхөн устгасан компьютер, төхөөрөмжүүд (phpMyAdmin дээр хүснэгт шиг харагдана).
     *  - «Архивт»: computers хүснэгтэд deleted_at-тай (Устгасан хэсгээс сэргээж болно)
     *  - «Бүрмөсөн устгасан»: computers-оос устсан, мэдээллийг нь үйлдлийн түүхээс (activity_logs) авна
     * View тул өгөгдөл хуулагддаггүй, үргэлж бодит байдалтай таарна. Сэргээсэн төхөөрөмж автоматаар гарна.
     */
    public function up(): void
    {
        // View нь MySQL-ийн JSON функц ашиглана; SQLite (тест) дээр computers-ийг дахин үүсгэхэд view эвдэрдэг тул алгасна
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $category = fn (string $column) => 'CASE '.$column.' '.collect(EquipmentCategory::cases())
            ->map(fn ($c) => "WHEN '{$c->value}' THEN '{$c->label()}'")->implode(' ').' ELSE '.$column.' END';

        // Бүрмөсөн устгахад хадгалсан бүтэн мэдээлэл (_snapshot); хуучин түүхэнд байхгүй бол зөвхөн нэр
        $snap = fn (string $field) => "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(l.changes, '$._snapshot.{$field}')), 'null')";

        DB::statement('DROP VIEW IF EXISTS deleted_computers');
        DB::statement(<<<SQL
            CREATE VIEW deleted_computers AS
            SELECT
                'Архивт (сэргээж болно)' AS deletion_status,
                c.id AS computer_id,
                c.inventory_number,
                c.name,
                {$category('c.category')} AS category,
                c.brand,
                c.model,
                c.serial_number,
                c.department,
                c.location,
                c.assigned_to,
                c.purchase_price,
                c.deleted_at,
                u.name AS deleted_by
            FROM computers c
            LEFT JOIN users u ON u.id = c.deleted_by
            WHERE c.deleted_at IS NOT NULL

            UNION ALL

            SELECT
                'Бүрмөсөн устгасан',
                l.subject_id,
                {$snap('inventory_number')},
                COALESCE({$snap('name')}, l.subject_label),
                {$category($snap('category'))},
                {$snap('brand')},
                {$snap('model')},
                {$snap('serial_number')},
                {$snap('department')},
                {$snap('location')},
                {$snap('assigned_to')},
                {$snap('purchase_price')},
                l.created_at,
                u.name
            FROM activity_logs l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE l.action = 'force_deleted'
              AND l.subject_type LIKE '%Computer'
              -- Түүхээс сэргээсэн бол computers-д дахин байгаа тул энд гарахгүй
              AND NOT EXISTS (SELECT 1 FROM computers x WHERE x.id = l.subject_id)
              -- Нэг төхөөрөмжийг хэд хэдэн удаа сэргээж, устгасан бол хамгийн сүүлийнх
              AND l.id = (SELECT MAX(l2.id) FROM activity_logs l2
                          WHERE l2.action = 'force_deleted' AND l2.subject_type = l.subject_type AND l2.subject_id = l.subject_id)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS deleted_computers');
    }
};
