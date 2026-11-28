<?php

namespace App\Support;

use App\Models\AdminLog;
use App\Models\Asset;
use App\Models\Folder;
use App\Models\User;

/** Records admin changes to folders, files and students in the activity log. */
class AdminActivity
{
    public static function register(): void
    {
        Folder::created(fn (Folder $f) => AdminLog::add('created', 'folder', $f->id, 'Created folder '.FolderTree::path($f->id)));
        Folder::updated(function (Folder $f) {
            $changes = array_keys(array_diff_key($f->getChanges(), ['updated_at' => 1]));
            if ($changes === ['sort_order']) {
                return; // reordering is noise
            }
            $what = $f->wasChanged('parent_id') ? 'Moved folder' : ($f->wasChanged('name') ? 'Renamed folder' : 'Edited folder');
            AdminLog::add('updated', 'folder', $f->id, $what.' '.$f->getOriginal('name').' → '.FolderTree::path($f->id));
        });
        Folder::deleted(fn (Folder $f) => AdminLog::add('deleted', 'folder', $f->id, 'Deleted folder '.$f->name.' (and everything inside)'));

        Asset::created(fn (Asset $a) => AdminLog::add('created', 'file', $a->id, 'Added file "'.$a->name.'" to '.FolderTree::path($a->folder_id)));
        Asset::updated(function (Asset $a) {
            $changes = array_keys(array_diff_key($a->getChanges(), ['updated_at' => 1, 'notified_at' => 1, 'sort_order' => 1, 'size_bytes' => 1, 'file_ext' => 1, 'storage_key' => 1, 'size_label' => 1]));
            if (! $changes) {
                return;
            }
            $what = $a->wasChanged('folder_id') ? 'Moved file' : 'Edited file';
            AdminLog::add('updated', 'file', $a->id, $what.' "'.$a->name.'" ('.implode(', ', $changes).') in '.FolderTree::path($a->folder_id));
        });
        Asset::deleted(fn (Asset $a) => AdminLog::add('deleted', 'file', $a->id, 'Deleted file "'.$a->name.'"'));

        User::created(fn (User $u) => AdminLog::add('created', 'student', $u->id, 'Added teacher '.$u->name.' ('.$u->phone.')'));
        User::updated(function (User $u) {
            $changes = array_keys(array_diff_key($u->getChanges(), ['updated_at' => 1, 'remember_token' => 1, 'last_login_at' => 1, 'login_count' => 1]));
            if (! $changes) {
                return;
            }
            $what = in_array('password', $changes, true) ? 'Reset password of' : 'Edited teacher';
            AdminLog::add('updated', 'student', $u->id, $what.' '.$u->name.' ('.implode(', ', array_diff($changes, ['password'])).')');
        });
        User::deleted(fn (User $u) => AdminLog::add('deleted', 'student', $u->id, 'Deleted teacher '.$u->name.' ('.$u->phone.')'));
    }
}
