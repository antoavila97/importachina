<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const STATUS_PENDIENTE = 'pendiente';

    public const STATUS_PAGADO = 'pagado';

    public const STATUS_ENVIADO = 'enviado';

    public const STATUS_ENTREGADO = 'entregado';

    /** Estados que cuentan como venta para el reporte. */
    public const SOLD_STATUSES = [
        self::STATUS_PAGADO,
        self::STATUS_ENVIADO,
        self::STATUS_ENTREGADO,
    ];

    /** Transiciones permitidas, en orden. */
    public const STATUSES = [
        self::STATUS_PENDIENTE,
        self::STATUS_PAGADO,
        self::STATUS_ENVIADO,
        self::STATUS_ENTREGADO,
    ];

    /** Estados a los que el pedido no puede volver. */
    public const CLOSED_STATUSES = [
        self::STATUS_ENTREGADO,
    ];

    protected $fillable = [
        'user_id',
        'total',
        'status',
        'shipping_address',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isSold(): bool
    {
        return in_array($this->status, self::SOLD_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Estados que el vendedor puede elegir desde la pantalla de detalle.
     */
    public function nextStatuses(): array
    {
        return array_values(array_filter(
            self::STATUSES,
            fn (string $status) => $status !== $this->status,
        ));
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDIENTE => 'bg-yellow-100 text-yellow-800',
            self::STATUS_PAGADO => 'bg-blue-100 text-blue-800',
            self::STATUS_ENVIADO => 'bg-indigo-100 text-indigo-800',
            self::STATUS_ENTREGADO => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
