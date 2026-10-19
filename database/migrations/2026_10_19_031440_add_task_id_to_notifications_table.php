<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('task_id')
                ->nullable()
                ->after('worker_id')
                ->constrained('tasks')
                ->nullOnDelete();

            $table->index([
                'worker_id',
                'task_id',
                'read_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex([
                'worker_id',
                'task_id',
                'read_at',
            ]);

            $table->dropConstrainedForeignId('task_id');
        });
    }
};
