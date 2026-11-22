<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'worker_attendances_ordinary_day_unique';

    public function up(): void
    {
        $ordinary = DB::table('worker_attendances')
            ->whereNull('deleted_at')
            ->whereNull('machine_assignment_id')
            ->whereIn('worker_id', DB::table('workers')->select('id')->where('role', 'worker'));

        $duplicates = (clone $ordinary)
            ->select('company_id', 'worker_id')
            ->selectRaw('date(date) as attendance_day')
            ->groupBy('company_id', 'worker_id', DB::raw('date(date)'))
            ->havingRaw('count(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $conflicts = $duplicates->map(fn ($duplicate) => [
                'company_id' => $duplicate->company_id,
                'worker_id' => $duplicate->worker_id,
                'date' => $duplicate->attendance_day,
                'attendance_ids' => (clone $ordinary)
                    ->where('company_id', $duplicate->company_id)
                    ->where('worker_id', $duplicate->worker_id)
                    ->whereDate('date', $duplicate->attendance_day)
                    ->orderBy('id')->pluck('id')->all(),
            ]);

            throw new RuntimeException('Ordinary worker attendance conflicts: '.$conflicts->toJson());
        }

        if (! Schema::hasColumn('worker_attendances', 'ordinary_worker_day')) {
            Schema::table('worker_attendances', fn (Blueprint $table) => $table->date('ordinary_worker_day')->nullable());
        }

        $ordinary->whereNull('ordinary_worker_day')->update(['ordinary_worker_day' => DB::raw('date(date)')]);

        if (! Schema::hasIndex('worker_attendances', self::INDEX)) {
            Schema::table('worker_attendances', function (Blueprint $table) {
                $table->unique(['company_id', 'worker_id', 'ordinary_worker_day'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        Schema::table('worker_attendances', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
            $table->dropColumn('ordinary_worker_day');
        });
    }
};
