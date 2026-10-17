<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->boolean('time_not_fixed')->default(false)->after('duration_minutes');
            $table->boolean('open_ended')->default(false)->after('time_not_fixed');
            $table->boolean('technician_pending')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['time_not_fixed', 'open_ended', 'technician_pending']);
        });
    }
};
