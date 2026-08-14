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
        Schema::create('excavator_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('machine_assignment_id')
                ->unique()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('worker_id')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamp('site_manager_started_at')->nullable();
            $table->timestamp('site_manager_finished_at')->nullable();
            $table->timestamp('operator_started_at')->nullable();
            $table->timestamp('operator_finished_at')->nullable();
            $table->decimal('work_hours', 8, 2)->default(0);
            $table->decimal('start_work_hours', 8, 2)->nullable()->default(null);
            $table->decimal('finish_work_hours', 8, 2)->nullable()->default(null);
            $table->decimal('fuel_added', 10, 2)->default(0);
            $table->decimal('fuel_remaining', 10, 2)->nullable();
            $table->text('note_site_manager')->nullable();
            $table->text('note_operator')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excavator_logs');
    }
};
