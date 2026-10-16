<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('particulier');
            $table->string('phone');
            $table->string('phone_alt')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('commune')->nullable();
            $table->string('wilaya')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('phone');
            $table->index(['wilaya', 'commune']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
