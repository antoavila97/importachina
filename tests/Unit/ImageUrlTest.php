<?php

namespace Tests\Unit;

use App\Support\ImageUrl;
use PHPUnit\Framework\TestCase;

class ImageUrlTest extends TestCase
{
    public function test_quita_el_sufijo_de_transformacion_de_aliexpress(): void
    {
        $this->assertSame(
            'https://ae-pic-a1.aliexpress-media.com/kf/Sa951f486e6b04ce286d86f78dc9a65453.jpg',
            ImageUrl::upgrade('https://ae-pic-a1.aliexpress-media.com/kf/Sa951f486e6b04ce286d86f78dc9a65453.jpg_220x220q75.jpg_.avif'),
        );
    }

    public function test_quita_la_transformacion_pegada_detras_de_la_query(): void
    {
        $this->assertSame(
            'https://ae-pic-a1.aliexpress-media.com/kf/S5afd0954d35544ab97bec35e42841a53o.jpg',
            ImageUrl::upgrade('https://ae-pic-a1.aliexpress-media.com/kf/S5afd0954d35544ab97bec35e42841a53o.jpg?has_lang=1&ver=2_220x220q75.jpg_.avif'),
        );
    }

    public function test_quita_el_sufijo_webp(): void
    {
        $this->assertSame(
            'https://ae01.alicdn.com/kf/abc.jpg',
            ImageUrl::upgrade('https://ae01.alicdn.com/kf/abc.jpg_.webp'),
        );
    }

    public function test_conserva_la_url_original_si_no_tiene_transformacion(): void
    {
        $url = 'https://cdn.importachina.com/auriculares.jpg';

        $this->assertSame($url, ImageUrl::upgrade($url));
    }

    public function test_conserva_las_queries_propias_del_sitio(): void
    {
        $url = 'https://cdn.importachina.com/imagen.jpeg?version=2&v=10';

        $this->assertSame($url, ImageUrl::upgrade($url));
    }

    public function test_no_toca_urls_sin_extension_de_imagen(): void
    {
        $this->assertSame('https://placehold.co/600x400?text=AE+001', ImageUrl::upgrade('https://placehold.co/600x400?text=AE+001'));
        $this->assertSame('images/placeholder.svg', ImageUrl::upgrade('images/placeholder.svg'));
    }

    public function test_recorta_en_la_extension_mas_larga(): void
    {
        $this->assertSame('https://cdn.x/a.jpeg', ImageUrl::upgrade('https://cdn.x/a.jpeg_400x400.jpg'));
    }

    public function test_acepta_mayusculas_y_devuelve_null_sin_valor(): void
    {
        $this->assertSame('https://cdn.x/A.JPG', ImageUrl::upgrade('  https://cdn.x/A.JPG_100x100.JPG  '));
        $this->assertNull(ImageUrl::upgrade(null));
        $this->assertNull(ImageUrl::upgrade('   '));
    }
}
