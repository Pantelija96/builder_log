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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('construction_site_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('name', 255)->index();

            $table->string('type', 100)
                ->nullable()
                ->index();

            $table->string('original_name');
            $table->string('path', 500);
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');

            $table->foreignId('uploaded_by')
                ->constrained('workers')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['construction_site_id', 'created_at'],
                'documents_site_created_at_index'
            );

            $table->index(
                ['uploaded_by', 'created_at'],
                'documents_uploader_created_at_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
