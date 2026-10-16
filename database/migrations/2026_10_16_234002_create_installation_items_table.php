<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('installation_id')->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->string('serial_number')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit')->default('unité');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('installation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_items');
    }
};
