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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
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

            $table->nullableMorphs('auditable');

            $table->json('old_values')
                ->nullable();

            $table->json('new_values')
                ->nullable();

            $table->text('reason')
                ->nullable();

            $table->timestamp('created_at')
                ->useCurrent();

            $table->index(
                ['company_id', 'created_at'],
                'audit_logs_company_created_at_index'
            );

            $table->index(
                ['actor_id', 'created_at'],
                'audit_logs_actor_created_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
