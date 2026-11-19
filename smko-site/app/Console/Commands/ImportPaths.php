<?php

namespace App\Console\Commands;

use App\Support\StoragePaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportPaths extends Command
{
    protected $signature = 'smko:import-paths
        {file : Path to storage-paths.json}
        {--verify : Check every key exists in the bucket and record its real size}';

    protected $description = 'Apply exact bucket keys collected from the old site';

    public function handle(): int
    {
        $result = StoragePaths::apply(StoragePaths::load($this->argument('file')));
        $this->info("Applied {$result['applied']} keys ({$result['unknown']} for files not in the database).");

        if ($this->option('verify')) {
            $this->verify();
        }

        $this->table(['Files', 'Count'], [
            ['With a known bucket location', DB::table('assets')->whereNotNull('file_ext')->count()],
            ['Still unknown (not TEXT)', DB::table('assets')->whereNull('file_ext')->where('type', '!=', 'TEXT')->count()],
        ]);

        return self::SUCCESS;
    }

    protected function verify(): void
    {
        $disk = Storage::disk(config('smko.disk'));
        $rows = DB::table('assets')->whereNotNull('storage_key')->get(['id', 'storage_key']);
        $bar = $this->output->createProgressBar($rows->count());
        $missing = [];

        foreach ($rows as $row) {
            try {
                DB::table('assets')->where('id', $row->id)->update(['size_bytes' => $disk->size($row->storage_key)]);
            } catch (\Throwable) {
                $missing[] = $row;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        if ($missing) {
            $this->warn(count($missing).' keys not found in the bucket — cleared so those files show as unavailable:');
            foreach (array_slice($missing, 0, 20) as $m) {
                $this->line("  #{$m->id}  {$m->storage_key}");
            }
            DB::table('assets')->whereIn('id', array_column($missing, 'id'))->update(['file_ext' => null, 'storage_key' => null]);
        } else {
            $this->info('All '.$rows->count().' keys exist in the bucket.');
        }
    }
}
