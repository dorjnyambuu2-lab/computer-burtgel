<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Төхөөрөмж захиалгын мөрүүд: нэг захиалгад олон төхөөрөмж (Процессор ×1, UPS ×2), Админ мөр бүрийг тусад нь
     * зөвшөөрөх / татгалзах / хүлээлгэн өгнө. Одоо байгаа захиалга («Дэлгэц ×2» гарчигтай) нэг мөртэй болно.
     */
    public function up(): void
    {
        Schema::create('asset_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_request_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->unsignedTinyInteger('quantity')->default(1);
            $t->string('status', 20)->default('pending'); // pending, approved, delivered, rejected
            $t->string('note', 500)->nullable();          // Админы хариу (татгалзсан шалтгаан г.м.)
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Захиалгын төлөв → мөрийн төлөв
        $map = ['pending' => 'pending', 'in_progress' => 'approved', 'done' => 'delivered', 'rejected' => 'rejected'];
        DB::table('asset_requests')->where('type', 'request')->orderBy('id')->get()->each(function ($r) use ($map) {
            preg_match('/^(.*?)(?:\s*×(\d+))?$/u', trim((string) $r->subject), $m);
            DB::table('asset_request_items')->insert([
                'asset_request_id' => $r->id,
                'name' => mb_substr(($m[1] ?? '') !== '' ? $m[1] : 'Төхөөрөмж', 0, 120),
                'quantity' => max(1, min(255, (int) ($m[2] ?? 1))),
                'status' => $map[$r->status] ?? 'pending',
                'handled_by' => $r->status === 'pending' ? null : $r->handled_by,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_request_items');
    }
};
