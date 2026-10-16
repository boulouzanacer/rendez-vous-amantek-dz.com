<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('designation');
            $table->text('description')->nullable();
            $table->string('unit')->default('unité');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['quote_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
