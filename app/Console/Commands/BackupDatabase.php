<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Zrzut bazy do storage/app/backups (gzip, JSON-lines na tabelę), bez mysqldump,
 * żeby działało na hostingu współdzielonym. Homelab pobiera go przez /backup/latest lub SFTP.
 */
class BackupDatabase extends Command
{
    protected $signature = 'agency:backup';

    protected $description = 'Tworzy kopię zapasową bazy danych';

    private const SKIP = ['sessions', 'cache', 'cache_locks', 'jobs'];

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $name = 'backups/db-'.now()->format('Ymd-His').'.jsonl.gz';
        $gz = gzopen($disk->path($name), 'wb6');

        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (in_array($table, self::SKIP, true)) {
                continue;
            }
            gzwrite($gz, json_encode(['table' => $table])."\n");
            $query = DB::table($table);
            $order = Schema::hasColumn($table, 'id') ? 'id' : Schema::getColumnListing($table)[0];
            $query->orderBy($order)->chunk(500, function ($rows) use ($gz) {
                foreach ($rows as $row) {
                    gzwrite($gz, json_encode($row, JSON_UNESCAPED_UNICODE)."\n");
                }
            });
        }
        gzclose($gz);

        $all = collect($disk->files('backups'))->sort()->values();
        $all->slice(0, max(0, $all->count() - config('agency.backup_keep')))->each(fn ($f) => $disk->delete($f));

        SystemLog::record('backup', 'Database backup created', ['file' => $name, 'size' => $disk->size($name)]);
        $this->info($name);

        return self::SUCCESS;
    }
}
