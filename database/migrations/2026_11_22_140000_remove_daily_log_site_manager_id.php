<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $missingMembership = DB::table('daily_logs')
            ->whereNotExists(fn ($members) => $members->selectRaw('1')
                ->from('daily_log_site_managers')
                ->whereColumn('daily_log_site_managers.daily_log_id', 'daily_logs.id')
                ->whereColumn('daily_log_site_managers.site_manager_id', 'daily_logs.site_manager_id'))
            ->exists();

        $invalidCompany = DB::table('daily_log_site_managers as members')
            ->join('daily_logs', 'daily_logs.id', '=', 'members.daily_log_id')
            ->join('workers', 'workers.id', '=', 'members.site_manager_id')
            ->whereColumn('workers.company_id', '<>', 'daily_logs.company_id')
            ->exists();

        if ($missingMembership || $invalidCompany) {
            throw new RuntimeException('Cannot remove daily log ownership: verify missing legacy memberships and company mismatches.');
        }

        Schema::table('daily_logs', function (Blueprint $table) {
            $table->dropForeign(['site_manager_id']);
            $table->dropColumn('site_manager_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Restore a pre-M4 snapshot to recover the original singular daily log attribution.');
    }
};
