<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Adds or updates students from an Excel (.xlsx) or CSV file.
 *
 * Columns (first row = headings, any order, case-insensitive):
 *   name*, phone*, telegram_id, folders, access_ends
 * folders: full paths separated by ";" — e.g. "Adults / E4E1; Teens"  (folder numbers also work)
 * access_ends: a date like 2027-01-31 (optional, applies to the folders on that row)
 *
 * Existing students (same phone) keep their password; their name is updated and folders are added.
 * New students start with the password "smko".
 */
class StudentImporter
{
    public const HEADINGS = ['name', 'phone', 'telegram_id', 'folders', 'access_ends'];

    /** @return array{created:int, updated:int, grants:int, errors:array<int,string>, rows:int} */
    public static function import(string $path, bool $dryRun = false): array
    {
        $rows = static::read($path);
        $result = ['created' => 0, 'updated' => 0, 'grants' => 0, 'errors' => [], 'rows' => 0];
        if (! $rows) {
            $result['errors'][1] = 'The file is empty.';

            return $result;
        }

        $head = array_map(fn ($h) => Str::snake(trim(Str::lower((string) $h))), array_shift($rows));
        foreach (['name', 'phone'] as $required) {
            if (! in_array($required, $head, true)) {
                $result['errors'][1] = "Missing a \"{$required}\" column. Expected headings: ".implode(', ', self::HEADINGS).'.';

                return $result;
            }
        }

        $byPath = static::folderLookup();
        DB::beginTransaction();
        foreach ($rows as $i => $cells) {
            $line = $i + 2;
            $r = [];
            foreach ($head as $k => $h) {
                $r[$h] = trim((string) ($cells[$k] ?? ''));
            }
            if (implode('', $r) === '') {
                continue;
            }
            $result['rows']++;

            $phone = preg_replace('/\s+/', '', $r['phone'] ?? '');
            $name = $r['name'] ?? '';
            if ($phone === '' || $name === '') {
                $result['errors'][$line] = 'Name and phone are required.';

                continue;
            }

            $folderIds = [];
            foreach (preg_split('/[;\n]+/', $r['folders'] ?? '') as $f) {
                $f = trim($f);
                if ($f === '') {
                    continue;
                }
                $id = ctype_digit($f) ? (int) $f : ($byPath[static::normalisePath($f)] ?? null);
                if (! $id || ! isset(FolderTree::nodes()[$id])) {
                    $result['errors'][$line] = "Folder not found: \"{$f}\".";

                    continue 2;
                }
                $folderIds[] = $id;
            }

            $ends = null;
            if (($r['access_ends'] ?? '') !== '') {
                try {
                    $ends = is_numeric($r['access_ends'])   // Excel serial date
                        ? Carbon::create(1899, 12, 30)->addDays((int) $r['access_ends'])->endOfDay()
                        : Carbon::parse($r['access_ends'])->endOfDay();
                } catch (\Throwable) {
                    $result['errors'][$line] = "Couldn't read the date \"{$r['access_ends']}\".";

                    continue;
                }
            }

            $user = User::where('phone', $phone)->first();
            if ($user) {
                $user->fill(['name' => $name] + (($r['telegram_id'] ?? '') !== '' ? ['telegram_id' => $r['telegram_id']] : []))->save();
                $result['updated']++;
            } else {
                $user = User::create([
                    'name' => $name, 'phone' => $phone,
                    'telegram_id' => ($r['telegram_id'] ?? '') ?: $phone,
                    'password' => User::DEFAULT_PASSWORD, 'must_change_password' => true,
                ]);
                $result['created']++;
            }

            foreach ($folderIds as $fid) {
                $user->folders()->syncWithoutDetaching([$fid => ['expires_at' => $ends, 'granted_at' => now()]]);
                if ($ends) {
                    $user->folders()->updateExistingPivot($fid, ['expires_at' => $ends]);
                }
                $result['grants']++;
            }
        }

        $dryRun ? DB::rollBack() : DB::commit();
        FolderTree::flush();

        return $result;
    }

    /** @return array<int, array<int, mixed>> */
    protected static function read(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $reader = $ext === 'csv' ? new CsvReader : new XlsxReader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(function ($v) {
                    return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
                }, $row->toArray());
            }
            break; // first sheet only
        }
        $reader->close();

        return $rows;
    }

    protected static function normalisePath(string $p): string
    {
        $parts = preg_split('/\s*(?:\/|›|>|»|\\\\)\s*/u', Str::lower(trim($p)));

        return implode('/', array_filter(array_map('trim', $parts), 'strlen'));
    }

    /** @return array<string, int> normalised path => folder id */
    protected static function folderLookup(): array
    {
        $map = [];
        foreach (array_keys(FolderTree::nodes()) as $id) {
            $map[static::normalisePath(FolderTree::path($id))] ??= $id;
        }

        return $map;
    }

    /** Writes an example file people can fill in. */
    public static function writeTemplate(string $path): void
    {
        $writer = new \OpenSpout\Writer\XLSX\Writer;
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(self::HEADINGS));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Sara Ahmadi', '9123456789', '', 'Adults / E4E1; General', '2027-06-30']));
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['Karwan Aziz', '7700112233', '', 'Kids', '']));
        $writer->close();
    }
}
