<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorizations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('en_preparation');
            $table->string('wilaya')->nullable();
            $table->string('commune')->nullable();
            $table->string('address')->nullable();
            $table->boolean('has_inside_cameras')->default(false);
            $table->boolean('has_outside_cameras')->default(false);
            $table->json('inside_cameras')->nullable();
            $table->json('outside_cameras')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorizations');
    }
};
