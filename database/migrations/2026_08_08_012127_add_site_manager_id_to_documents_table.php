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
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('site_manager_id')
                ->nullable()
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->index(
                ['site_manager_id', 'created_at'],
                'documents_site_manager_created_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign('site_manager_id');
            $table->dropForeign('site_manager_created_at_index');
        });
    }
};
