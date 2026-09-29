<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('worker_id')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);

            $table->date('date')->index();

            $table->text('note')->nullable();

            $table->foreignId('created_by')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['company_id', 'worker_id', 'date'],
                'worker_payments_company_worker_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_payments');
    }
};
