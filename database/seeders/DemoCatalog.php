<?php

namespace Database\Seeders;

/**
 * Catálogo de ejemplo de ImportaChina.
 *
 * Vive aca y no dentro del seeder porque lo consumen dos lugares:
 * DemoSalesSeeder lo crea cuando la tabla products esta vacia, y la migracion
 * rewrite_demo_catalog_to_spanish reescribe en el sitio los productos que la
 * demo vieja creó con la factory. Si cada uno tuviera su propia copia, en
 * cualquier momento podrian terminar mostrando productos distintos.
 */
final class DemoCatalog
{
    /**
     * Productos de ejemplo: [categoria, titulo, costo, margen %].
     *
     * @var list<array{0: string, 1: string, 2: float, 3: int}>
     */
    public const PRODUCTS = [
        ['Hogar', 'Organizador de almacenaje plegable 3 niveles', 12.90, 45],
        ['Hogar', 'Set de 6 potes herméticos de cocina', 18.50, 35],
        ['Hogar', 'Lámpara de mesa LED con control táctil', 9.75, 60],
        ['Electrónica', 'Auriculares inalámbricos Bluetooth 5.3', 14.30, 50],
        ['Electrónica', 'Cargador rápido USB-C 65W GaN', 16.80, 40],
        ['Electrónica', 'Power bank 20000mAh con carga rápida', 21.40, 35],
        ['Moda', 'Bolso tote de lona con cierre impermeable', 11.20, 55],
        ['Accesorios', 'Gafas de sol polarizadas UV400', 7.90, 70],
    ];

    /**
     * Descripción de los productos de ejemplo.
     */
    public const DESCRIPTION =
        'Producto de ejemplo para la demo. El Catálogo real se llena con la '
        .'sincronizacion de la API de AliExpress (HU-05).';

    /**
     * Categorias en el orden en que aparecen, por si hay que crearlas.
     *
     * @return list<string>
     */
    public static function categoryNames(): array
    {
        return array_values(array_unique(array_column(self::PRODUCTS, 0)));
    }

    /**
     * El producto de ejemplo que le toca a un indice dado. Da la vuelta al
     * Catálogo si hay menos productos en la base que en la lista.
     *
     * @return array{0: string, 1: string, 2: float, 3: int}
     */
    public static function forIndex(int $index): array
    {
        return self::PRODUCTS[$index % count(self::PRODUCTS)];
    }
}
