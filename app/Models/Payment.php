<?php

namespace App\Models;

use App\Http\Controllers\Vendedor\PaymentController;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_VOIDED,
    ];

    protected $fillable = [
        'order_id',
        'method',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_VOIDED => 'Anulado',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'bg-green-100 text-green-800',
            self::STATUS_VOIDED => 'bg-gray-100 text-gray-800 line-through',
            default => 'bg-yellow-100 text-yellow-800',
        };
    }

    public function methodLabel(): string
    {
        return PaymentController::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
