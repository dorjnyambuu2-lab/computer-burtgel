<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('computer_id')->constrained()->cascadeOnDelete();
            // Үйлдлийг хийсэн IT ажилтан
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Олгосон / буцаасан ажилтан (хариуцагч)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20); // assigned | returned | repair
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['computer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computer_events');
    }
};