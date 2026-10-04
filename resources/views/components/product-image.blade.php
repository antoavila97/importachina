@props([
    'src' => null,
    'alt' => '',
    'class' => '',
])

{{--
    Imagen de producto con fallback.

    Hace dos cosas:
      1. Si no hay URL, usa el placeholder local (public/images/placeholder.svg).
         Los productos de demo se crean con image_url en null, asi que antes
         se veia el texto alt pelado.
      2. Si la URL existe pero falla (imagen de AliExpress caida, 403, hotlink
         bloqueado), onerror cambia al placeholder. Sin esto el navegador
         muestra el texto alt y queda roto.

    El segundo punto importa: aunque la API devuelva URLs, un 403 del CDN
    dejaba las tarjetas vacias en la demo.
--}}
@php
    $placeholder = asset('images/placeholder.svg');
    $final = $src ?: $placeholder;
@endphp

<img
    src="{{ $final }}"
    alt="{{ $alt }}"
    loading="lazy"
    decoding="async"
    @if ($src) onerror="this.onerror=null;this.src='{{ $placeholder }}'" @endif
    {{ $attributes->merge(['class' => $class]) }}
/>
