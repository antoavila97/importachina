<?php

namespace App\Services;

use App\Exceptions\AliExpressApiException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;

/**
 * Cliente de la API de afiliados de AliExpress (TOP / Open Platform).
 *
 * Documentacion: developers.aliexpress.com -> "API calling process" y
 * "Signature algorithm". La peticion va SIEMPRE por POST a
 * https://api-sg.aliexpress.com/sync con Content-Type
 * application/x-www-form-urlencoded, aunque el metodo se llame product.query.
 */
class AliExpressService
{
    public const SIGN_MD5 = 'md5';

    public const SIGN_HMAC = 'hmac';

    public const PRODUCT_QUERY = 'aliexpress.affiliate.product.query';

    /** La API rechaza peticiones con una diferencia de tiempo mayor a 10 minutos. */
    private const PROTOCOL_VERSION = '2.0';

    private const TIMEOUT = 30;

    public function __construct(private readonly HttpFactory $http) {}

    /**
     * ¿Hay credenciales cargadas? El panel lo muestra antes de permitir sincronizar.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.aliexpress.app_key'))
            && filled(config('services.aliexpress.app_secret'));
    }

    /**
     * Trae una pagina de productos de afiliados.
     *
     * @param  array<string, mixed>  $extra  parametros propios del metodo de la API
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws AliExpressApiException
     */
    public function queryProducts(string $keyword = '', int $page = 1, int $pageSize = 50, array $extra = []): array
    {
        if (! $this->isConfigured()) {
            throw AliExpressApiException::missingCredentials();
        }

        $params = array_merge([
            'method' => self::PRODUCT_QUERY,
            'app_key' => $this->appKey(),
            'timestamp' => $this->timestamp(),
            'v' => self::PROTOCOL_VERSION,
            'format' => 'json',
            'sign_method' => $this->signMethod(),
            'page_no' => (string) max(1, $page),
            'page_size' => (string) max(1, min(50, $pageSize)),
        ], array_filter([
            'keywords' => $keyword !== '' ? $keyword : null,
            'tracking_id' => config('services.aliexpress.tracking_id') ?: null,
        ], fn ($value) => $value !== null), $extra);

        $params['sign'] = $this->sign($params);

        $response = $this->http
            ->asForm()
            ->timeout(self::TIMEOUT)
            ->retry(2, 250)
            ->post($this->baseUrl(), $params);

        if ($response->failed()) {
            throw $response->throw();
        }

        $payload = $response->json() ?? [];

        $this->guardAgainstApiError($payload);

        $body = $payload['aliexpress_affiliate_product_query_response']
            ?? $payload['resp_result']
            ?? [];

        // La API responde products.product; algunos escenarios traen products
        // como lista directa, asi que se aceptan las dos formas.
        $items = $this->normalizeAll(
            data_get($body, 'products.product', data_get($body, 'products'))
        );

        $total = data_get($body, 'total_results');

        return [
            'items' => $items,
            'total' => is_numeric($total) ? (int) $total : count($items),
        ];
    }

    /**
     * Firma la peticion segun el algoritmo de AliExpress (TOP).
     *
     * 1. Se descartan los parametros vacios y la propia clave "sign".
     * 2. Se ordenan las claves por ASCII.
     * 3. Se concatena "clave + valor" sin separador.
     * 4. md5:    md5(secret + cadena + secret)
     *    hmac:   hmac_md5(cadena, secret)
     * 5. El hexadecimal se devuelve en mayusculas.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws AliExpressApiException
     */
    public function sign(array $params, ?string $secret = null): string
    {
        $secret ??= (string) config('services.aliexpress.app_secret');

        if ($secret === '') {
            throw AliExpressApiException::missingCredentials();
        }

        $params = array_filter(
            $params,
            fn ($value, $key) => $key !== 'sign' && $value !== null && $value !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        ksort($params, SORT_STRING);

        $concatenated = '';

        foreach ($params as $key => $value) {
            $concatenated .= $key.$value;
        }

        $digest = $this->signMethod() === self::SIGN_HMAC
            ? hash_hmac('md5', $concatenated, $secret)
            : md5($secret.$concatenated.$secret);

        return strtoupper($digest);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AliExpressApiException
     */
    private function guardAgainstApiError(array $payload): void
    {
        $error = $payload['error_response'] ?? $payload['error'] ?? null;

        if (! is_array($error)) {
            return;
        }

        throw AliExpressApiException::apiError(
            (int) ($error['code'] ?? $error['error_code'] ?? 0),
            (string) ($error['msg'] ?? $error['message'] ?? 'sin mensaje'),
            (string) ($error['sub_msg'] ?? ''),
        );
    }

    /**
     * La API devuelve "products": {"product": [ ... ]}. Algunas respuestas ya
     * vienen como lista, asi que se aceptan las dos formas.
     *
     * @param  mixed  $products
     * @return list<array<string, mixed>>
     */
    private function normalizeAll($products): array
    {
        if (! is_array($products) || $products === []) {
            return [];
        }

        // Lista directa de productos.
        if (array_is_list($products)) {
            return array_values(array_map(fn ($product) => $this->normalizeProduct($product), $products));
        }

        // Un unico producto suelto.
        if (isset($products['product_id'])) {
            return [$this->normalizeProduct($products)];
        }

        // Envoltorio {"product": [...]}.
        $list = $products['product'] ?? [];

        if (! is_array($list)) {
            return [];
        }

        return array_values(array_map(
            fn ($product) => $this->normalizeProduct($product),
            array_is_list($list) ? $list : [$list],
        ));
    }

    /**
     * Traduce el objeto de la API a los nombres de la tabla products.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function normalizeProduct(array $product): array
    {
        $price = $product['target_sale_price'] ?? $product['sale_price'] ?? 0;

        return [
            'external_id' => (string) ($product['product_id'] ?? ''),
            'title' => (string) ($product['product_title'] ?? ''),
            'description' => trim(strip_tags((string) ($product['product_description'] ?? ''))),
            'image_url' => $this->firstImage($product['product_main_image_url'] ?? null),
            'extra_images' => $this->extractGallery($product['product_small_image_urls'] ?? []),
            'source_url' => $product['product_detail_url'] ?? null,
            'promotion_link' => $product['promotion_link'] ?? null,
            // HU-05: el precio se guarda tal cual lo devuelve la API (USD).
            'cost_price' => round((float) $price, 2),
            'category_name' => $product['first_level_category_name']
                ?? $product['second_level_category_name']
                ?? null,
            'category_external_id' => $product['first_level_category_id']
                ?? $product['second_level_category_id']
                ?? null,
            'shop_url' => $product['shop_url'] ?? null,
            'evaluate_rate' => $product['evaluate_rate'] ?? null,
        ];
    }

    /**
     * La columna image_url es de texto: si la API manda una lista, se toma la primera.
     */
    private function firstImage($value): ?string
    {
        if (is_string($value)) {
            return trim($value) !== '' ? trim($value) : null;
        }

        return $this->extractGallery($value)[0] ?? null;
    }

    /**
     * product_small_image_urls llega como lista o como string separado por comas.
     *
     * @return list<string>
     */
    private function extractGallery($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($url) => is_string($url) ? trim($url) : null,
            $value,
        )));
    }

    /**
     * AliExpress exige la hora de China (GMT+8) con formato yyyy-MM-dd HH:mm:ss.
     */
    private function timestamp(): string
    {
        return Date::now('Asia/Shanghai')->format('Y-m-d H:i:s');
    }

    private function appKey(): string
    {
        return (string) config('services.aliexpress.app_key');
    }

    private function baseUrl(): string
    {
        return (string) config('services.aliexpress.base_url');
    }

    private function signMethod(): string
    {
        $method = (string) config('services.aliexpress.sign_method', self::SIGN_MD5);

        return $method === self::SIGN_HMAC ? self::SIGN_HMAC : self::SIGN_MD5;
    }
}
