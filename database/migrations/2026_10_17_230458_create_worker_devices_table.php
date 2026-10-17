<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('platform', 20);
            /*
             * Jedinstveni ID instalacije/uređaja koji će Android
             * kasnije slati backendu.
             *
             * Omogućava nam da pronađemo isti uređaj čak i kada
             * Firebase promeni njegov FCM token.
             */
            $table->string('device_uuid', 100)->nullable();
            /*
             * Firebase Cloud Messaging registration token.
             *
             * Ne koristimo običan VARCHAR(255), jer token može
             * biti duži. 512 nam ostavlja dovoljno prostora.
             */
            $table->string('fcm_token', 512);
            $table->string('device_name')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            /*
             * Ako Firebase kasnije kaže da token više nije validan,
             * ne moramo da brišemo istoriju uređaja.
             */
            $table->boolean('is_active')->default(true);
            $table->timestamps();


            $table->unique('fcm_token');

            $table->index(
                ['worker_id', 'is_active'], 'worker_devices_worker_active_index'
            );
            $table->index('device_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_devices');
    }
};
