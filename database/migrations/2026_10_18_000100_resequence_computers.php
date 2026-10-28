<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Анх дэс дугаарыг завсаргүй болгодог байсан (Computer::resequence). Одоогийн resequence нь хожим нэмэгдсэн
     * баганууд (branch_id, kit_parent_id, category)-ыг ашигладаг тул энд дуудвал шинэ өгөгдлийн санд (MySQL)
     * «Unknown column» алдаа өгнө. Дугаарлалтыг дараагийн migration-ууд (sequence_number_per_branch,
     * resequence_without_mouse_keyboard) хийдэг тул энд юу ч хийхгүй.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
