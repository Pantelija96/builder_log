<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontractor_logs', function (Blueprint $table) {
            $table->dateTime('started_at')
                ->nullable()
                ->after('worker_count');

            $table->dateTime('finished_at')
                ->nullable()
                ->after('started_at');

            $table->text('note')
                ->nullable()
                ->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('subcontractor_logs', function (Blueprint $table) {
            $table->dropColumn([
                'started_at',
                'finished_at',
                'note',
            ]);
        });
    }
};
