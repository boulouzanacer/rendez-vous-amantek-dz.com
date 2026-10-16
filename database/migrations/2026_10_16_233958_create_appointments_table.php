<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('planifie');
            $table->string('priority')->default('normale');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('address')->nullable();
            $table->string('commune')->nullable();
            $table->string('wilaya')->nullable();
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('scheduled_at');
            $table->index('status');
            $table->index(['user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
