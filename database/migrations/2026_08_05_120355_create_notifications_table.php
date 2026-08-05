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
        Schema::create('notifications', function (Blueprint $table) {

            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('worker_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('type',50)->index();

            $table->string('title',255);

            $table->text('message');

            $table->json('payload')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(
                ['worker_id','read_at'],
                'notifications_worker_read_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
