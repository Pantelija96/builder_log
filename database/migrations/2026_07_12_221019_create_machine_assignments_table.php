<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('machine_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('daily_log_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('construction_site_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('site_manager_id')
                ->nullable()
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('machine_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('worker_id')
                ->nullable()
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamp('site_manager_started_at')
                ->nullable();

            $table->timestamp('site_manager_finished_at')
                ->nullable();

            $table->timestamp('operator_started_at')
                ->nullable();

            $table->timestamp('operator_finished_at')
                ->nullable();

            $table->date('date')
                ->index();

            $table->foreignId('created_by')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'machine_id',
                'date',
            ]);

            $table->index([
                'construction_site_id',
                'date',
            ]);

            $table->index([
                'site_manager_id',
                'date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_assignments');
    }
};
