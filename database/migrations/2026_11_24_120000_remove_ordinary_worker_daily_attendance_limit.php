<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_attendances', function (Blueprint $table) {
            $table->index(['company_id', 'worker_id', 'started_at'], 'worker_attendances_worker_interval_index');
            $table->dropUnique('worker_attendances_ordinary_day_unique');
            $table->dropColumn('ordinary_worker_day');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restore a pre-migration backup to reapply the daily limit; adjacent periods may now exist.');
    }
};
