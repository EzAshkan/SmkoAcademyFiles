<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Upload extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'folder_id', 'filename', 'title', 'description', 'size', 'md5', 'received', 'notify', 'status', 'asset_id'];

    protected function casts(): array
    {
        return ['notify' => 'boolean', 'size' => 'integer', 'received' => 'integer'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function partPath(): string
    {
        return 'upload-chunks/'.$this->id.'.part';
    }
}
