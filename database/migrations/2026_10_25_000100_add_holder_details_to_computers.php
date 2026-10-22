<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * «Байршил ба хариуцагч»: нэгж, албан тушаал, хариуцагчийн овог, нэр
     * (салбар = branch_id, алба = department аль хэдийн бий)
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->string('unit')->nullable()->after('department');            // Нэгж
            $table->string('position')->nullable()->after('unit');              // Албан тушаал
            $table->string('holder_surname')->nullable()->after('assigned_user_id'); // Хариуцагчийн овог
            $table->string('holder_name')->nullable()->after('holder_surname');      // Хариуцагчийн нэр
        });
    }

    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropColumn(['unit', 'position', 'holder_surname', 'holder_name']);
        });
    }
};
