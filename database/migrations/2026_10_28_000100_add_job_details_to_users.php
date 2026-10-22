<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Хэрэглэгчийн овог, нэгж, алба, албан тушаал (компьютерийн «Байршил ба хариуцагч»-д автоматаар бөглөгдөнө) */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('name');
            $table->string('unit')->nullable()->after('branch_id');
            $table->string('department')->nullable()->after('unit');
            $table->string('position')->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['surname', 'unit', 'department', 'position']);
        });
    }
};
