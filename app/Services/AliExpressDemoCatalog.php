<?php

namespace App\Services;

/**
 * Catalogo local que emula la respuesta de aliexpress.affiliate.product.query.
 *
 * La API real no se puede usar desde Bolivia: el registro pide verificar un
 * numero de celular y el pais no figura entre los soportados. El modo
 * demostracion alimenta el mismo codigo de importacion (parseo, paginacion,
 * no duplicacion y precio de venta) con estos datos, para poder mostrar
 * HU-05 de punta a punta sin credenciales.
 *
 * Los items usan la forma exacta de la API: product_id, product_title,
 * sale_price, first_level_category_id... El importador no distingue uno de
 * otro.
 */
class AliExpressDemoCatalog
{
    /**
     * Prefijo propio para que los ids de demo nunca colisionen con los de la
     * API real y se puedan borrar sin tocar los productos verdaderos.
     */
    public const ID_PREFIX = 'demo-';

    /**
     * 24 productos: dos paginas de la API (el page_size maximo es 20).
     *
     * @var list<array{0: string, 1: string, 2: float, 3: string, 4: string}>
     */
    private const ITEMS = [
        ['1001', 'Auriculares inalámbricos Bluetooth 5.3 con estuche de carga', 12.90, '100009', 'Accesorios para celulares'],
        ['1002', 'Audífonos gamer con micrófono y luz RGB', 18.50, '100009', 'Accesorios para celulares'],
        ['1003', 'Cable USB-C cargador rápido 65W de nylon trenzado', 4.20, '100009', 'Accesorios para celulares'],
        ['1004', 'Funda antiburbujas para smartphone (unidad)', 1.80, '100009', 'Accesorios para celulares'],
        ['1005', 'Soporte de escritorio para celular y tablet', 3.60, '100009', 'Accesorios para celulares'],
        ['2001', 'Power bank 20000mAh con carga rápida 22.5W', 15.40, '100013', 'Electrónica'],
        ['2002', 'Smartwatch deportivo pantalla AMOLED y monitor cardíaco', 24.90, '100013', 'Electrónica'],
        ['2003', 'Mouse inalámbrico recargable silencioso', 6.75, '100013', 'Electrónica'],
        ['2004', 'Teclado mecánico RGB 87 teclas switches azules', 21.00, '100013', 'Electrónica'],
        ['2005', 'Parlante Bluetooth portátil resistente al agua', 13.20, '100013', 'Electrónica'],
        ['2006', 'Webcam 1080p con micrófono y cortina de privacidad', 16.80, '100013', 'Electrónica'],
        ['2007', 'Cargador de pared doble USB 30W con protector de voltaje', 7.90, '100013', 'Electrónica'],
        ['2008', 'Lámpara LED de escritorio con brazo articulado', 11.30, '100013', 'Electrónica'],
        ['3001', 'Licuadora portátil USB recargable 6 palas', 9.60, '100020', 'Hogar y cocina'],
        ['3002', 'Organizador de escritorio de madera con cajones', 8.40, '100020', 'Hogar y cocina'],
        ['3003', 'Set de 3 ollas antibalcon con tapa de vidrio', 27.50, '100020', 'Hogar y cocina'],
        ['3004', 'Purificador de aire portátil con filtro HEPA', 32.00, '100020', 'Hogar y cocina'],
        ['3005', 'Cortinas blackout térmicas para ventana (2 unidades)', 14.75, '100020', 'Hogar y cocina'],
        ['3006', 'Set de 5 bolsas de storage al vacío', 5.90, '100020', 'Hogar y cocina'],
        ['4001', 'Mochila impermeable para laptop de 15.6 pulgadas', 19.90, '100017', 'Deportes y ocio'],
        ['4002', 'Botella térmica de acero inoxidable 750ml', 7.20, '100017', 'Deportes y ocio'],
        ['4003', 'Correa inteligente para perro reflectante', 4.80, '100017', 'Deportes y ocio'],
        ['4004', 'Colchoneta de yoga antideslizante 6mm', 10.50, '100017', 'Deportes y ocio'],
        ['4005', 'Linterna frontal recargable con 4 modos de luz', 6.30, '100017', 'Deportes y ocio'],
    ];

    /** Page size maximo que acepta la API real. */
    public const PAGE_SIZE_MAX = 20;

    public function total(): int
    {
        return count(self::ITEMS);
    }

    /**
     * Una pagina de productos ya en la forma cruda de la API.
     *
     * La palabra clave no se aplica: el catalogo demo es fijo y el filtrado
     * real lo hace el buscador del propio catálogo (HU-09).
     *
     * @return list<array<string, mixed>>
     */
    public function page(int $page, int $pageSize): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(self::PAGE_SIZE_MAX, $pageSize));

        $slice = array_slice(self::ITEMS, ($page - 1) * $pageSize, $pageSize);

        return array_values(array_map(
            fn (array $item, int $index) => $this->toApiShape($item, ($page - 1) * $pageSize + $index),
            $slice,
            array_keys($slice),
        ));
    }

    /**
     * @param  array{0: string, 1: string, 2: float, 3: string, 4: string}  $item
     * @return array<string, mixed>
     */
    private function toApiShape(array $item, int $position): array
    {
        [$id, $title, $price, $categoryId, $categoryName] = $item;

        return [
            'product_id' => self::ID_PREFIX.$id,
            'product_title' => $title,
            'product_description' => $this->description($title),
            'product_main_image_url' => null,
            'product_small_image_urls' => [],
            'product_detail_url' => 'https://es.aliexpress.com/wholesale?SearchText='.urlencode($title),
            'promotion_link' => null,
            'sale_price' => number_format($price, 2, '.', ''),
            'sale_price_currency' => 'USD',
            'first_level_category_id' => $categoryId,
            'first_level_category_name' => $categoryName,
            'shop_url' => 'https://es.aliexpress.com/store/3255036',
            'evaluate_rate' => (string) (4 + (($position % 9) / 10)),
        ];
    }

    private function description(string $title): string
    {
        return "<p>{$title}. Producto de demostración importado por HU-05 desde el "
            .'catálogo local: la API de AliExpress no está disponible en Bolivia. '
            .'Precio de costo en USD; el precio de venta se calcula con el margen '
            .'configurado.</p>';
    }
}
