<?php

namespace App\Console\Commands;

use App\Exceptions\AliExpressApiException;
use App\Models\ApiSyncLog;
use App\Models\Category;
use App\Models\Product;
use App\Services\AliExpressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * HU-05: importa productos desde la API de AliExpress.
 *
 * La API se consulta por lotes y el catálogo se sirve desde la base propia: si la
 * API falla, la tienda sigue funcionando.
 */
class SyncAliExpressProducts extends Command
{
    /** Criterio de aceptacion: entre 20 y 50 productos por sincronizacion. */
    public const MIN_LIMIT = 20;

    public const MAX_LIMIT = 50;

    protected $signature = 'app:sync-aliexpress-products
                            {--keyword= : Texto a buscar en AliExpress}
                            {--limit=20 : Cantidad de productos a importar (20 a 50)}';

    protected $description = 'Importa productos desde la API de AliExpress al catalogo';

    public function handle(AliExpressService $api): int
    {
        $keyword = (string) ($this->option('keyword') ?: config('services.aliexpress.default_keyword'));
        $limit = $this->resolveLimit();
        $pageSize = min($limit, self::MAX_LIMIT);

        $this->components->info("Sincronizando desde AliExpress: keyword \"{$keyword}\", hasta {$limit} producto(s).");

        if ($api->isDemo()) {
            $this->components->info('Modo demostración: se usa el catálogo local, porque la API real exige verificar un celular y Bolivia no está entre los países soportados.');
        }

        try {
            $result = $this->import($api, $keyword, $limit, $pageSize);
        } catch (AliExpressApiException $e) {
            return $this->logFailure($e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->logFailure("Fallo inesperado: {$e->getMessage()}");
        }

        $this->logSuccess($result, $api->isDemo());

        $this->newLine();
        $this->line($this->summary($result));
        $this->components->twoColumnDetail('Productos importados', (string) $result['imported']);
        $this->components->twoColumnDetail('Actualizados', (string) $result['updated']);
        $this->components->twoColumnDetail('Omitidos', (string) $result['skipped']);
        $this->components->twoColumnDetail('Total', (string) $result['processed']);

        if ($result['imported'] + $result['updated'] === 0) {
            $this->components->warn('No se importo ningun producto: revisa el keyword o los permisos de la API.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Recorre paginas hasta llegar al limite pedido.
     *
     * @return array{imported: int, updated: int, skipped: int, processed: int, pages: int}
     */
    private function import(AliExpressService $api, string $keyword, int $limit, int $pageSize): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $processed = 0;
        $page = 1;
        $previousFirstId = null;

        while ($processed < $limit) {
            $response = $api->queryProducts(
                keyword: $keyword,
                page: $page,
                pageSize: min($pageSize, $limit - $processed),
            );

            if ($response['items'] === []) {
                break;
            }

            // La API puede devolver menos de lo pedido (page_size maximo 20) y eso
            // no significa que se acabou el catalogo: se sigue paginando. Si la
            // pagina repite el primer producto, la API esta ignorando page_no.
            $firstId = $response['items'][0]['external_id'] ?? null;

            if ($firstId !== null && $firstId === $previousFirstId) {
                break;
            }

            $previousFirstId = $firstId;

            foreach ($response['items'] as $item) {
                if ($processed >= $limit) {
                    break;
                }

                $outcome = $this->store($item);

                match ($outcome) {
                    'created' => $imported++,
                    'updated' => $updated++,
                    default => $skipped++,
                };

                $processed++;
            }

            $page++;
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'processed' => $processed,
            'pages' => $page,
        ];
    }

    /**
     * Un producto ya importado se actualiza en vez de duplicarse (criterio de HU-05).
     *
     * @param  array<string, mixed>  $item
     * @return 'created'|'updated'|'skipped'
     */
    private function store(array $item): string
    {
        $externalId = $item['external_id'] ?? '';

        if ($externalId === '' || ($item['title'] ?? '') === '' || (float) ($item['cost_price'] ?? 0) <= 0) {
            return 'skipped';
        }

        $category = $this->resolveCategory($item);

        return DB::transaction(function () use ($item, $externalId, $category) {
            // firstOrNew en vez de updateOrCreate para no pisar el stock que
            // ajusto el administrador: la API no manda stock.
            $product = Product::firstOrNew(['external_id' => $externalId]);
            $existed = $product->exists;

            $product->fill([
                'category_id' => $category?->id,
                'title' => $item['title'],
                'description' => $item['description'] ?: null,
                'cost_price' => $item['cost_price'],
                'margin_pct' => (float) config('services.aliexpress.margin_pct', 30),
                'stock' => $existed ? $product->stock : 100,
                'image_url' => $item['image_url'] ?? null,
                'source_url' => $item['source_url'] ?? null,
                'active' => true,
                'synced_at' => now(),
            ]);

            // HU-07: el precio de venta sale del modelo, no de una formula duplicada.
            $product->syncSalePrice()->save();

            $this->storeGallery($product, $item['extra_images'] ?? []);

            return $existed ? 'updated' : 'created';
        });
    }

    /**
     * Las categorias de la API se traducen a las nuestras por external_category_id.
     *
     * @param  array<string, mixed>  $item
     */
    private function resolveCategory(array $item): ?Category
    {
        $name = trim((string) ($item['category_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        return Category::firstOrCreate(
            ['external_category_id' => (string) ($item['category_external_id'] ?? $name)],
            [
                'name' => $name,
                'slug' => Category::uniqueSlug($name),
            ],
        );
    }

    /**
     * @param  list<string>  $urls
     */
    private function storeGallery(Product $product, array $urls): void
    {
        if ($urls === []) {
            return;
        }

        $existing = $product->images()->pluck('url')->all();

        foreach (array_values($urls) as $position => $url) {
            if ($url === '' || in_array($url, $existing, true)) {
                continue;
            }

            $product->images()->create([
                'url' => $url,
                'position' => $position + 1,
            ]);
        }
    }

    private function resolveLimit(): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < self::MIN_LIMIT) {
            $this->components->warn("El limite se ajusto a {$limit}: el minimo es ".self::MIN_LIMIT.'.');

            $limit = self::MIN_LIMIT;
        }

        if ($limit > self::MAX_LIMIT) {
            $this->components->warn("El limite se ajusto a {$limit}: el maximo es ".self::MAX_LIMIT.'.');

            $limit = self::MAX_LIMIT;
        }

        return $limit;
    }

    /**
     * @param  array{imported: int, updated: int, skipped: int, processed: int, pages: int}  $result
     */
    private function summary(array $result): string
    {
        return sprintf(
            '%d nuevo(s), %d actualizado(s), %d omitido(s) en %d pagina(s).',
            $result['imported'],
            $result['updated'],
            $result['skipped'],
            $result['pages'],
        );
    }

    /**
     * @param  array{imported: int, updated: int, skipped: int, processed: int, pages: int}  $result
     */
    private function logSuccess(array $result, bool $demo = false): void
    {
        ApiSyncLog::create([
            'user_id' => auth()->id(),
            'items_imported' => $result['imported'] + $result['updated'],
            'status' => ApiSyncLog::STATUS_SUCCESS,
            'message' => $this->summary($result).($demo ? ' (modo demostración)' : ''),
        ]);
    }

    private function logFailure(string $message): int
    {
        ApiSyncLog::create([
            'user_id' => auth()->id(),
            'items_imported' => 0,
            'status' => ApiSyncLog::STATUS_FAILED,
            'message' => $message,
        ]);

        $this->components->error($message);

        return self::FAILURE;
    }
}
