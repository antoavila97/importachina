<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    private const TOP_LIMIT = 5;

    public function index(ReportRequest $request)
    {
        $tipo = $request->tipo();

        return view('admin.reports.index', [
            'tipo' => $tipo,
            'tipos' => ReportRequest::TYPES,
            'report' => $this->build($tipo, $request),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'from' => $request->from(),
            'to' => $request->to(),
        ]);
    }

    public function pdf(ReportRequest $request)
    {
        $tipo = $request->tipo();
        $report = $this->build($tipo, $request);

        $pdf = Pdf::loadView('admin.reports.pdf', [
            'report' => $report,
            'generatedAt' => now(),
            'generatedBy' => $request->user()?->name ?? 'ImportaChina',
        ])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        // Pie de página en todas las hojas (coordenadas en puntos, A4 = 595 x 842).
        $canvas = $dompdf->getCanvas();
        $canvas->page_text(42, 815, $this->footerText($request), 'Helvetica', 7.5, [0.42, 0.45, 0.50]);
        $canvas->page_text(470, 815, 'Página {PAGE_NUM} de {PAGE_COUNT}', 'Helvetica', 7.5, [0.42, 0.45, 0.50]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte-'.$tipo.'-'.now()->format('Y-m-d').'.pdf"',
        ]);
    }

    private function build(string $tipo, ReportRequest $request): array
    {
        return match ($tipo) {
            'pedidos' => $this->ordersReport($request),
            'productos' => $this->productsReport($request),
            'categorias' => $this->categoriesReport($request),
            'usuarios' => $this->usersReport($request),
            default => $this->salesReport($request),
        };
    }

    private function footerText(ReportRequest $request): string
    {
        $who = $request->user()?->name ?? 'ImportaChina';
        $who = mb_strimwidth($who, 0, 55, '…');

        return 'Generado el '.now()->format('d/m/Y H:i').' por '.$who;
    }

    private function period(ReportRequest $request): string
    {
        return 'Del '.$request->from()->format('d/m/Y').' al '.$request->to()->format('d/m/Y');
    }

    /* ------------------------------------------------------------------ */
    /* Ventas (HU-16, el reporte que ya existía)                           */
    /* ------------------------------------------------------------------ */

    private function salesReport(ReportRequest $request): array
    {
        $from = $request->from();
        $to = $request->to();

        $orders = Order::query()
            ->whereIn('status', Order::SOLD_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'total', 'status', 'created_at']);

        $orderIds = $orders->pluck('id');
        $revenue = (float) $orders->sum('total');

        return [
            'title' => 'Reporte de ventas',
            'period' => $this->period($request),
            'summary' => [
                ['label' => 'Total vendido', 'value' => 'Bs '.number_format($revenue, 2)],
                ['label' => 'Pedidos vendidos', 'value' => (string) $orders->count()],
                ['label' => 'Ticket promedio', 'value' => 'Bs '.number_format($orders->isEmpty() ? 0 : $revenue / $orders->count(), 2)],
                ['label' => 'Unidades vendidas', 'value' => (string) $this->unitsSold($orderIds)],
            ],
            'note' => 'Cuentan como venta los pedidos en estado Pagado, Enviado y Entregado. '
                .'Quedan '.$this->pendingCount($from, $to).' pedido(s) pendientes en el periodo.',
            'sections' => [
                [
                    'type' => 'table',
                    'title' => self::TOP_LIMIT.' productos más vendidos',
                    'columns' => [
                        ['label' => '#', 'align' => 'left'],
                        ['label' => 'Producto', 'align' => 'left'],
                        ['label' => 'Unidades', 'align' => 'right'],
                        ['label' => 'Vendido', 'align' => 'right'],
                    ],
                    'rows' => $this->topProducts($orderIds)->values()->map(fn ($item, $index) => [
                        (string) ($index + 1),
                        $item->title,
                        (string) (int) $item->units,
                        'Bs '.number_format((float) $item->revenue, 2),
                    ])->all(),
                    'empty' => 'No hay ventas en el periodo seleccionado.',
                ],
                [
                    'type' => 'bars',
                    'title' => 'Venta por día',
                    'rows' => $this->dailySeries($orders, $from, $to)
                        ->filter(fn (array $day) => $day['total'] > 0)
                        ->map(fn (array $day) => [
                            'label' => $day['date']->format('d/m'),
                            'total' => $day['total'],
                            'orders' => $day['orders'],
                            'value' => 'Bs '.number_format($day['total'], 2),
                        ])->values()->all(),
                    'empty' => 'No hay ventas en el periodo seleccionado.',
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Pedidos                                                             */
    /* ------------------------------------------------------------------ */

    private function ordersReport(ReportRequest $request): array
    {
        $orders = Order::query()
            ->with('user:id,name')
            ->withCount('items')
            ->whereBetween('created_at', [$request->from(), $request->to()])
            ->when($request->estado(), fn ($query, $estado) => $query->where('status', $estado))
            ->orderByDesc('created_at')
            ->get();

        $total = (float) $orders->sum('total');
        $period = $this->period($request);

        if ($estado = $request->estado()) {
            $period .= ' · Estado: '.ucfirst($estado);
        }

        return [
            'title' => 'Reporte de pedidos',
            'period' => $period,
            'summary' => [
                ['label' => 'Pedidos', 'value' => (string) $orders->count()],
                ['label' => 'Monto total', 'value' => 'Bs '.number_format($total, 2)],
                ['label' => 'Ticket promedio', 'value' => 'Bs '.number_format($orders->isEmpty() ? 0 : $total / $orders->count(), 2)],
                ['label' => 'Pendientes', 'value' => (string) $orders->where('status', Order::STATUS_PENDIENTE)->count()],
            ],
            'note' => 'Se listan todos los estados del período; el monto total suma los pedidos que aparecen abajo.',
            'sections' => [[
                'type' => 'table',
                'title' => 'Detalle de pedidos',
                'columns' => [
                    ['label' => 'N.º', 'align' => 'left'],
                    ['label' => 'Fecha', 'align' => 'left'],
                    ['label' => 'Cliente', 'align' => 'left'],
                    ['label' => 'Estado', 'align' => 'left'],
                    ['label' => 'Ítems', 'align' => 'right'],
                    ['label' => 'Total', 'align' => 'right'],
                ],
                'rows' => $orders->map(fn (Order $order) => [
                    '#'.$order->id,
                    $order->created_at->format('d/m/Y H:i'),
                    $order->user?->name ?? '—',
                    $order->statusLabel(),
                    (string) $order->items_count,
                    'Bs '.number_format((float) $order->total, 2),
                ])->all(),
                'empty' => 'No hay pedidos en el periodo seleccionado.',
            ]],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Productos                                                           */
    /* ------------------------------------------------------------------ */

    private function productsReport(ReportRequest $request): array
    {
        $products = Product::query()
            ->with('category:id,name')
            ->when($request->categoriaId(), fn ($query, $id) => $query->where('category_id', $id))
            ->when($request->activo() !== 'todos', fn ($query) => $query->where('active', $request->activo() === '1'))
            ->when($request->stock() === 'con', fn ($query) => $query->where('stock', '>', 0))
            ->when($request->stock() === 'sin', fn ($query) => $query->where('stock', '<=', 0))
            ->orderBy('title')
            ->get();

        $sold = $this->soldByProduct($request->from(), $request->to());

        $inventory = $products->sum(fn (Product $product) => (float) $product->cost_price * $product->stock);

        return [
            'title' => 'Reporte de productos',
            'period' => $this->period($request).$this->productFiltersLabel($request),
            'summary' => [
                ['label' => 'Productos', 'value' => (string) $products->count()],
                ['label' => 'Activos', 'value' => (string) $products->where('active', true)->count()],
                ['label' => 'Sin stock', 'value' => (string) $products->where('stock', '<=', 0)->count()],
                ['label' => 'Valor del inventario', 'value' => 'Bs '.number_format($inventory, 2)],
            ],
            'note' => 'El valor del inventario se calcula a costo (costo × stock). Las unidades vendidas cuentan solo pedidos Pagado, Enviado y Entregado.',
            'sections' => [[
                'type' => 'table',
                'title' => 'Detalle de productos',
                'columns' => [
                    ['label' => 'Producto', 'align' => 'left'],
                    ['label' => 'Categoría', 'align' => 'left'],
                    ['label' => 'Costo', 'align' => 'right'],
                    ['label' => 'Margen', 'align' => 'right'],
                    ['label' => 'Precio', 'align' => 'right'],
                    ['label' => 'Stock', 'align' => 'right'],
                    ['label' => 'Estado', 'align' => 'left'],
                    ['label' => 'Vendidos', 'align' => 'right'],
                ],
                'rows' => $products->map(fn (Product $product) => [
                    $product->title,
                    $product->category?->name ?? '—',
                    'Bs '.number_format((float) $product->cost_price, 2),
                    number_format((float) $product->margin_pct, 2).'%',
                    'Bs '.number_format((float) $product->sale_price, 2),
                    (string) $product->stock,
                    $product->statusLabel(),
                    (string) (int) ($sold->get($product->id)->units ?? 0),
                ])->all(),
                'empty' => 'No hay productos con esos filtros.',
            ]],
        ];
    }

    private function soldByProduct($from, $to): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', Order::SOLD_STATUSES)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units, SUM(order_items.quantity * order_items.unit_price) as revenue')
            ->get()
            ->keyBy('product_id');
    }

    /* ------------------------------------------------------------------ */
    /* Categorías                                                          */
    /* ------------------------------------------------------------------ */

    private function categoriesReport(ReportRequest $request): array
    {
        $categories = Category::query()
            ->withCount([
                'products',
                'products as active_products_count' => fn ($query) => $query->where('active', true),
            ])
            ->orderBy('name')
            ->get();

        $sales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('orders.status', Order::SOLD_STATUSES)
            ->whereBetween('orders.created_at', [$request->from(), $request->to()])
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, SUM(order_items.quantity) as units, SUM(order_items.quantity * order_items.unit_price) as revenue')
            ->get()
            ->keyBy('category_id');

        $totalRevenue = (float) $sales->sum('revenue');

        return [
            'title' => 'Reporte de categorías',
            'period' => $this->period($request),
            'summary' => [
                ['label' => 'Categorías', 'value' => (string) $categories->count()],
                ['label' => 'Productos', 'value' => (string) $categories->sum('products_count')],
                ['label' => 'Unidades vendidas', 'value' => (string) (int) $sales->sum('units')],
                ['label' => 'Ingresos', 'value' => 'Bs '.number_format($totalRevenue, 2)],
            ],
            'note' => 'Los ingresos salen de los pedidos en estado Pagado, Enviado y Entregado. El porcentaje es la participación de cada categoría en esos ingresos.',
            'sections' => [[
                'type' => 'table',
                'title' => 'Detalle de categorías',
                'columns' => [
                    ['label' => 'Categoría', 'align' => 'left'],
                    ['label' => 'Productos', 'align' => 'right'],
                    ['label' => 'Activos', 'align' => 'right'],
                    ['label' => 'Unidades', 'align' => 'right'],
                    ['label' => 'Ingresos', 'align' => 'right'],
                    ['label' => 'Participación', 'align' => 'right'],
                ],
                'rows' => $categories->map(function (Category $category) use ($sales, $totalRevenue) {
                    $data = $sales->get($category->id);
                    $revenue = (float) ($data->revenue ?? 0);

                    return [
                        $category->name,
                        (string) $category->products_count,
                        (string) $category->active_products_count,
                        (string) (int) ($data->units ?? 0),
                        'Bs '.number_format($revenue, 2),
                        $totalRevenue > 0 ? number_format($revenue / $totalRevenue * 100, 1).'%' : '0.0%',
                    ];
                })->all(),
                'empty' => 'No hay categorías registradas.',
            ]],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Usuarios                                                            */
    /* ------------------------------------------------------------------ */

    private function usersReport(ReportRequest $request): array
    {
        $from = $request->filled('from') ? $request->from() : null;
        $to = $request->filled('to') ? $request->to() : null;

        $users = User::query()
            ->with('role:id,name')
            ->when($request->rolId(), fn ($query, $id) => $query->where('role_id', $id))
            ->when($request->situacion(), fn ($query, $situacion) => $query->where('status', $situacion))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderBy('name')
            ->get();

        $purchases = Order::query()
            ->whereIn('status', Order::SOLD_STATUSES)
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as orders_count, SUM(total) as total_spent')
            ->get()
            ->keyBy('user_id');

        $spent = $users->sum(fn (User $user) => (float) ($purchases->get($user->id)->total_spent ?? 0));

        return [
            'title' => 'Reporte de usuarios',
            'period' => $this->usersPeriod($request),
            'summary' => [
                ['label' => 'Usuarios', 'value' => (string) $users->count()],
                ['label' => 'Activos', 'value' => (string) $users->where('status', User::STATUS_ACTIVE)->count()],
                ['label' => 'Inactivos', 'value' => (string) $users->where('status', User::STATUS_INACTIVE)->count()],
                ['label' => 'Total comprado', 'value' => 'Bs '.number_format($spent, 2)],
            ],
            'note' => 'El total comprado suma los pedidos Pagado, Enviado y Entregado de cada usuario. Si no se indica fecha, se listan todas las altas.',
            'sections' => [[
                'type' => 'table',
                'title' => 'Detalle de usuarios',
                'columns' => [
                    ['label' => 'Nombre', 'align' => 'left'],
                    ['label' => 'Email', 'align' => 'left'],
                    ['label' => 'Rol', 'align' => 'left'],
                    ['label' => 'Estado', 'align' => 'left'],
                    ['label' => 'Alta', 'align' => 'left'],
                    ['label' => 'Pedidos', 'align' => 'right'],
                    ['label' => 'Total comprado', 'align' => 'right'],
                ],
                'rows' => $users->map(function (User $user) use ($purchases) {
                    $data = $purchases->get($user->id);

                    return [
                        $user->name,
                        $user->email,
                        $user->role?->name ?? '—',
                        $user->statusLabel(),
                        $user->created_at->format('d/m/Y'),
                        (string) (int) ($data->orders_count ?? 0),
                        'Bs '.number_format((float) ($data->total_spent ?? 0), 2),
                    ];
                })->all(),
                'empty' => 'No hay usuarios con esos filtros.',
            ]],
        ];
    }

    private function usersPeriod(ReportRequest $request): string
    {
        $from = $request->filled('from') ? $request->from() : null;
        $to = $request->filled('to') ? $request->to() : null;

        if ($from === null && $to === null) {
            return 'Todas las altas';
        }

        $text = $from !== null ? 'Desde '.$from->format('d/m/Y') : 'Hasta '.$to->format('d/m/Y');

        if ($from !== null && $to !== null) {
            $text = 'Altas del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y');
        }

        return $text;
    }

    private function productFiltersLabel(ReportRequest $request): string
    {
        $labels = [];

        if ($request->categoriaId()) {
            $labels[] = 'Categoría: '.(Category::find($request->categoriaId())?->name ?? '—');
        }

        if ($request->activo() !== 'todos') {
            $labels[] = 'Estado: '.($request->activo() === '1' ? 'Activos' : 'Inactivos');
        }

        if ($request->stock() !== 'todos') {
            $labels[] = 'Stock: '.($request->stock() === 'con' ? 'Con stock' : 'Sin stock');
        }

        return $labels === [] ? '' : ' · '.implode(' · ', $labels);
    }

    /* ------------------------------------------------------------------ */
    /* Cálculos del reporte de ventas                                      */
    /* ------------------------------------------------------------------ */

    private function unitsSold(Collection $orderIds): int
    {
        if ($orderIds->isEmpty()) {
            return 0;
        }

        return (int) OrderItem::whereIn('order_id', $orderIds)->sum('quantity');
    }

    private function pendingCount($from, $to): int
    {
        return Order::query()
            ->where('status', Order::STATUS_PENDIENTE)
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    private function topProducts(Collection $orderIds)
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereIn('order_items.order_id', $orderIds)
            ->groupBy('products.id', 'products.title')
            ->select('products.id as product_id', 'products.title')
            ->selectRaw('SUM(order_items.quantity) as units')
            ->selectRaw('SUM(order_items.quantity * order_items.unit_price) as revenue')
            ->orderByDesc('units')
            ->orderBy('products.title')
            ->limit(self::TOP_LIMIT)
            ->get();
    }

    private function dailySeries(Collection $orders, $from, $to): Collection
    {
        $byDay = $orders
            ->groupBy(fn (Order $order) => $order->created_at->format('Y-m-d'))
            ->map(fn (Collection $group) => [
                'total' => (float) $group->sum('total'),
                'orders' => $group->count(),
            ]);

        $days = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $days[] = [
                'date' => $day->copy(),
                'total' => $byDay->get($key)['total'] ?? 0.0,
                'orders' => $byDay->get($key)['orders'] ?? 0,
            ];
        }

        return collect($days);
    }
}
