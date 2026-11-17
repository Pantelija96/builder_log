<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('initial_data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('import_key', 64)->unique();
            $table->char('fingerprint', 64)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initial_data_imports');
    }
};
