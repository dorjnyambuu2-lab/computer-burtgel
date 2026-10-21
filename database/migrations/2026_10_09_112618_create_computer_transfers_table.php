<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computer_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('computer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_user_id')->constrained('users');
            $t->foreignId('to_user_id')->constrained('users');
            $t->text('reason')->nullable();
            $t->string('status')->default('pending'); // pending | approved | rejected
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_transfers');
    }
};