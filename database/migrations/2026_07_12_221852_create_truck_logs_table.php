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

            $table->foreignId('machine_id')
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

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('date')
                ->index();

            $table->timestamp('site_manager_started_at')
                ->nullable();

            $table->timestamp('site_manager_finished_at')
                ->nullable();

            $table->timestamp('operator_started_at')
                ->nullable();

            $table->timestamp('operator_finished_at')
                ->nullable();

            $table->decimal('start_mileage', 12, 2)
                ->nullable();

            $table->decimal('end_mileage', 12, 2)
                ->nullable();

            $table->decimal('fuel_added', 10, 2)
                ->default(0);

            $table->decimal('fuel_remaining', 10, 2)
                ->nullable();

            $table->text('note')
                ->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'machine_id',
                'date',
            ]);

            $table->index([
                'worker_id',
                'date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_logs');
    }
};
