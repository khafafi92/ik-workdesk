<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('scm_project_numbering')->where('code', 'COMP-19E001')->where('name', 'LTRO')
            ->update(['name' => 'LTRO-1B', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);

        DB::table('scm_cbes')->orderBy('id')->chunkById(200, function ($cbes): void {
            $numbers = DB::table('scm_requests')->whereIn('id', $cbes->pluck('request_id'))->pluck('number', 'id');
            foreach ($cbes as $cbe) {
                if (isset($numbers[$cbe->request_id])) {
                    DB::table('scm_cbes')->where('id', $cbe->id)->update(['number' => 'CBE-'.$numbers[$cbe->request_id]]);
                }
            }
        });
    }

    public function down(): void
    {
        // Nomor dokumen yang sudah diterbitkan tetap dipertahankan.
    }
};
