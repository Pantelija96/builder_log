<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->integer('display_order')->default(0)->change();
        });

        // A mixed dataset or an audited priority change indicates adoption.
        // Preserve every existing priority in that case, including ambiguous ones.
        $workers = DB::table('workers');
        if ((clone $workers)->where('display_order', '<>', 1)->exists()) {
            return;
        }

        $priorityWasUsed = DB::table('audit_logs')
            ->where('event', 'worker.log.updated')
            ->where(fn ($audit) => $audit->where('old_values->display_order', '<>', 1)
                ->orWhere('new_values->display_order', '<>', 1))
            ->exists();

        if (! $priorityWasUsed) {
            $workers->where('display_order', 1)->update(['display_order' => 0]);
        }
    }

    public function down(): void
    {
        // Restore the column default without rewriting priorities set since the upgrade.
        Schema::table('workers', function (Blueprint $table) {
            $table->integer('display_order')->default(1)->change();
        });
    }
};
