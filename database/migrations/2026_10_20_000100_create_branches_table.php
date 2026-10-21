<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Салбар: Супер админ салбаруудыг бүртгэнэ, салбар бүрийн Админ зөвхөн өөрийн салбарын
     * төхөөрөмж, ажилтныг харж удирдана. Одоо байгаа бүх өгөгдлийг «Төв салбар»-т оруулна.
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->nullable()->unique();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::table('computers', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->restrictOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('role')->constrained('branches')->restrictOnDelete();
        });

        $id = DB::table('branches')->insertGetId(['name' => 'Төв салбар', 'code' => 'TOV', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('computers')->update(['branch_id' => $id]);
        // Супер админ бүх салбарыг хариуцдаг тул салбаргүй
        DB::table('users')->where('role', '!=', 'superadmin')->update(['branch_id' => $id]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
        Schema::table('computers', fn (Blueprint $table) => $table->dropConstrainedForeignId('branch_id'));
        Schema::dropIfExists('branches');
    }
};
