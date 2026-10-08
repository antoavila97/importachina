<?php

namespace App\Models;

use App\Support\ImageUrl;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'external_id',
        'title',
        'description',
        'cost_price',
        'margin_pct',
        'sale_price',
        'price_locked',
        'stock',
        'image_url',
        'source_url',
        'active',
        'synced_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'price_locked' => 'boolean',
        'synced_at' => 'datetime',
        'cost_price' => 'decimal:2',
        'margin_pct' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * HU-08: la galería del formulario reemplaza a la registrada, en el orden
     * en que se cargaron (portada aparte, en image_url).
     *
     * @param  array<int, mixed>  $urls
     */
    public function syncGallery(array $urls): self
    {
        $urls = collect($urls)
            ->filter(fn ($url) => is_string($url) && trim($url) !== '')
            ->map(fn ($url) => ImageUrl::upgrade($url))
            ->filter()
            ->unique()
            ->values();

        ProductImage::where('product_id', $this->id)->delete();

        foreach ($urls as $position => $url) {
            $this->images()->create([
                'url' => $url,
                'position' => $position + 1,
            ]);
        }

        return $this;
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * HU-07: el precio de venta sale del costo y del margen.
     */
    public static function calculateSalePrice(float $costPrice, float $marginPct): float
    {
        return round($costPrice * (1 + $marginPct / 100), 2);
    }

    /**
     * Recalcula sale_price a partir de cost_price y margin_pct.
     * El administrador nunca escribe el precio de venta a mano.
     */
    public function syncSalePrice(): self
    {
        $this->sale_price = self::calculateSalePrice((float) $this->cost_price, (float) $this->margin_pct);

        return $this;
    }

    public function profit(): float
    {
        return round((float) $this->sale_price - (float) $this->cost_price, 2);
    }

    public function statusLabel(): string
    {
        return $this->active ? 'Activo' : 'Inactivo';
    }
}
