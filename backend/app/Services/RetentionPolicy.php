<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class RetentionPolicy {
    public function days(): int {
        $saved = DB::table('admin_settings')->where('key', 'finished_retention_days')->value('value');
        return max(0, (int) ($saved ?? config('admin.finished_retention_days')));
    }

    public function setDays(int $days): void {
        DB::table('admin_settings')->updateOrInsert(['key' => 'finished_retention_days'], ['value' => (string) $days]);
    }
}
