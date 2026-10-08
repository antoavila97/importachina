<?php

namespace App\Support;

/**
 * Las URLs de AliExpress vienen con un sufijo de transformacion que achica la
 * imagen y la convierte a avif, por ejemplo:
 *
 *     .../kf/abc.jpg_220x220q75.jpg_.avif   (7 KB, 220px)
 *     .../kf/abc.jpg                         (80 KB, original)
 *
 * Cortar en la primera extension de imagen recupera el archivo original.
 * Las URLs sin transformacion (placeholders, CDNs con query propia) quedan
 * intactas.
 */
class ImageUrl
{
    public static function upgrade(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/\.(?:jpe?g|png|webp|avif)/i', $url, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $url;
        }

        $end = $match[0][1] + strlen($match[0][0]);
        $base = substr($url, 0, $end);
        $rest = substr($url, $end);

        if ($rest === '') {
            return $base;
        }

        // Con query se respeta, salvo que la query traiga otra transformacion
        // de imagen: AliExpress a veces la pega detras del "?has_lang=...".
        if (str_contains($rest, '?') || str_contains($rest, '#')) {
            return preg_match('/\d+x\d+|\.(?:jpe?g|png|webp|avif)/i', $rest) === 1 ? $base : $url;
        }

        return $base;
    }
}
