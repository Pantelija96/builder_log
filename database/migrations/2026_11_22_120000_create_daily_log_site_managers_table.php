<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_log_site_managers')) {
            Schema::create('daily_log_site_managers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('daily_log_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
                $table->foreignId('site_manager_id')->constrained('workers')->cascadeOnUpdate()->restrictOnDelete();
                $table->timestamps();
                $table->unique(['daily_log_id', 'site_manager_id']);
                $table->index(['site_manager_id', 'daily_log_id']);
            });
        }

        // Raw rows include soft-deleted logs. The original log timestamp is only an estimate of when the manager joined.
        DB::table('daily_logs')
            ->select('id', 'site_manager_id', 'created_at')
            ->whereNotNull('site_manager_id')
            ->chunkById(100, function ($logs) {
                DB::table('daily_log_site_managers')->upsert(
                    $logs->map(fn ($log) => [
                        'daily_log_id' => $log->id,
                        'site_manager_id' => $log->site_manager_id,
                        'created_at' => $log->created_at,
                        'updated_at' => $log->created_at,
                    ])->all(),
                    ['daily_log_id', 'site_manager_id'],
                    ['daily_log_id'],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_log_site_managers');
    }
};
