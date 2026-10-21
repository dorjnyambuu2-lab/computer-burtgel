<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            // Засварын дараалал: байр (#1, #2 ...), явц, дараалалд орсон огноо
            $table->unsignedInteger('repair_position')->nullable()->index()->after('status');
            $table->string('repair_stage', 20)->nullable()->after('repair_position');
            $table->timestamp('repair_queued_at')->nullable()->after('repair_stage');

            // Хэн бүртгэсэн / хэн устгасан (шүүлт, архивт ашиглана)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Одоо засварт байгаа компьютеруудыг бүртгэсэн дарааллаар нь дараалалд оруулна
        $position = 0;
        DB::table('computers')->where('status', 'in_repair')->whereNull('deleted_at')->orderBy('updated_at')->orderBy('id')
            ->pluck('id')
            ->each(function ($id) use (&$position) {
                DB::table('computers')->where('id', $id)->update([
                    'repair_position' => ++$position,
                    'repair_stage' => 'queued',
                    'repair_queued_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['repair_position', 'repair_stage', 'repair_queued_at']);
        });
    }
};
