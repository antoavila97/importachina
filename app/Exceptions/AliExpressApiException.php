<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La API de AliExpress respondió con error (o no se pudo firmar la petición).
 * El mensaje que se guarda en api_sync_logs es el que devuelve la API, no uno genérico.
 */
class AliExpressApiException extends RuntimeException
{
    public static function missingCredentials(): self
    {
        return new self('Faltan las credenciales: revisar ALIEXPRESS_APP_KEY y ALIEXPRESS_APP_SECRET en el .env');
    }

    public static function apiError(int $code, string $message, string $subMessage = ''): self
    {
        $full = "AliExpress respondio error {$code}: {$message}";

        if ($subMessage !== '') {
            $full .= " ({$subMessage})";
        }

        return new self($full);
    }
}
