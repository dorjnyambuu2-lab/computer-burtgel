<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computers', function (Blueprint $table) {
            $table->id();
            $table->string('inventory_number')->unique();
            $table->string('name');
            $table->string('type', 20)->default('desktop')->index();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->string('cpu')->nullable();
            $table->unsignedSmallInteger('ram_gb')->nullable();
            $table->string('storage')->nullable();
            $table->string('os')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('department')->nullable()->index();
            $table->string('location')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computers');
    }
};
