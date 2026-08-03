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

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->decimal('work_hours', 8, 2)
                ->default(0);

            $table->decimal('fuel_added', 10, 2)
                ->default(0);

            $table->decimal('fuel_remaining', 10, 2)
                ->nullable();

            $table->text('note')->nullable();

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
