<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('machine_assignment_id')
                ->constrained('machine_assignments')
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
            $table->decimal('start_mileage', 12, 2)->nullable();
            $table->decimal('end_mileage', 12, 2)->nullable();
            $table->decimal('fuel_added', 10, 2)->nullable();
            $table->decimal('fuel_remaining', 10, 2)->nullable();
            $table->text('note_site_manager')->nullable();
            $table->text('note_operator')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('machine_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_logs');
    }
};
