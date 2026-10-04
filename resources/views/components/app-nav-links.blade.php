@props([
    // 'desktop' usa x-nav-link (barra horizontal), 'mobile' usa x-responsive-nav-link.
    'variant' => 'desktop',
])

@php
    $user = auth()->user();
    $isAdmin = (bool) $user?->hasRole('Administrador');
    $isSeller = $isAdmin || (bool) $user?->hasRole('Vendedor');

    /*
     * Una sola lista de enlaces para las dos variantes. Antes cada link estaba
     * escrito dos veces (barra de escritorio y menu movil) y se desincronizaban:
     * un texto corregido en un bloque dejaba el otro con el texto roto.
    */
    $links = [
        ['route' => 'catalog.index', 'label' => __('Catálogo'), 'active' => 'catalog.*', 'show' => true],
        ['route' => 'cart.index', 'label' => __('Carrito'), 'active' => 'cart.*', 'show' => $user !== null],
        ['route' => 'orders.index', 'label' => __('Mis pedidos'), 'active' => 'orders.*', 'show' => $user !== null],
        ['route' => 'vendedor.orders.index', 'label' => __('Pedidos'), 'active' => 'vendedor.*', 'show' => $isSeller],
        ['route' => 'admin.products.index', 'label' => __('Productos'), 'active' => 'admin.products.*', 'show' => $isAdmin],
        ['route' => 'admin.categories.index', 'label' => __('Categorías'), 'active' => 'admin.categories.*', 'show' => $isAdmin],
        ['route' => 'admin.users.index', 'label' => __('Usuarios'), 'active' => 'admin.users.*', 'show' => $isAdmin],
        ['route' => 'admin.reports.index', 'label' => __('Reportes'), 'active' => 'admin.reports.*', 'show' => $isAdmin],
        ['route' => 'admin.api-sync.index', 'label' => __('Sincronización API'), 'active' => 'admin.api-sync.*', 'show' => $isAdmin],
    ];

    $links = array_values(array_filter($links, fn (array $link) => $link['show']));
    $component = $variant === 'mobile' ? 'responsive-nav-link' : 'nav-link';
@endphp

@foreach ($links as $link)
    <x-dynamic-component
        :component="$component"
        :href="route($link['route'])"
        :active="request()->routeIs($link['active'])"
    >
        {{ $link['label'] }}
    </x-dynamic-component>
@endforeach