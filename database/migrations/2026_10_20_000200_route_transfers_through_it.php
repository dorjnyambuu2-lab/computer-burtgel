<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Шилжүүлэг заавал IT-аар дамжина: ажилтан → (IT хүлээн авч агуулахад) → дараагийн ажилтан.
     * Төлөв: pending (хүсэлт) → received (IT-д, агуулахад) → completed (дараагийн ажилтанд олгосон) | rejected.
     * to_user_id нь ажилтны санал болгосон дараагийн хариуцагч (заавал биш), issued_to_user_id нь бодитоор олгосон.
     */
    public function up(): void
    {
        Schema::table('computer_transfers', function (Blueprint $t) {
            $t->foreignId('to_user_id')->nullable()->change();
            $t->foreignId('received_by')->nullable()->after('decided_at')->constrained('users')->nullOnDelete();
            $t->timestamp('received_at')->nullable()->after('received_by');
            $t->foreignId('issued_to_user_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $t->foreignId('issued_by')->nullable()->after('issued_to_user_id')->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable()->after('issued_by');
        });
    }

    public function down(): void
    {
        Schema::table('computer_transfers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('issued_by');
            $t->dropConstrainedForeignId('issued_to_user_id');
            $t->dropConstrainedForeignId('received_by');
            $t->dropColumn(['received_at', 'completed_at']);
        });
    }
};
