<?php

namespace App\Models;

use Database\Factories\ApiSyncLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSyncLog extends Model
{
    /** @use HasFactory<ApiSyncLogFactory> */
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'items_imported',
        'status',
        'message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function statusLabel(): string
    {
        return $this->isSuccessful() ? 'Correcta' : 'Fallida';
    }

    public function statusBadgeClass(): string
    {
        return $this->isSuccessful()
            ? 'bg-green-100 text-green-800'
            : 'bg-red-100 text-red-800';
    }
}
