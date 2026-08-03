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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('site_manager_id')
                ->nullable()
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('construction_site_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('title', 255)->index();
            $table->text('description')->nullable();

            $table->string('priority', 50)
                ->nullable()
                ->index();

            $table->date('due_date')
                ->nullable()
                ->index();

            $table->timestamp('read_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable()
                ->index();

            $table->foreignId('created_by')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['site_manager_id', 'completed_at'],
                'tasks_manager_completed_index'
            );

            $table->index(
                ['construction_site_id', 'completed_at'],
                'tasks_site_completed_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
