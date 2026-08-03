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
        Schema::create('activity_logs', function (Blueprint $table) {
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
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('event', 50)
                ->index();

            $table->nullableMorphs('subject');

            $table->text('description');

            $table->date('date')
                ->index();

            $table->timestamp('created_at')
                ->useCurrent();

            $table->index(
                ['daily_log_id', 'created_at'],
                'activity_logs_daily_log_created_at_index'
            );

            $table->index(
                ['construction_site_id', 'date'],
                'activity_logs_site_date_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
