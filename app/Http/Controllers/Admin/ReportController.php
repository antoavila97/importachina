<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesReportRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    private const TOP_LIMIT = 5;

    public function index(SalesReportRequest $request)
    {
        $from = $request->from();
        $to = $request->to();

        $orders = Order::query()
            ->whereIn('status', Order::SOLD_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'total', 'status', 'created_at']);

        $orderIds = $orders->pluck('id');

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'ordersCount' => $orders->count(),
            'revenue' => (float) $orders->sum('total'),
            'averageTicket' => $orders->isEmpty() ? 0.0 : round((float) $orders->avg('total'), 2),
            'unitsSold' => $this->unitsSold($orderIds),
            'pendingCount' => $this->pendingCount($from, $to),
            'topProducts' => $this->topProducts($orderIds),
            'daily' => $this->dailySeries($orders, $from, $to),
        ]);
    }

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
            ->groupBy('products.id', 'products.title', 'products.image_url')
            ->select('products.id as product_id', 'products.title', 'products.image_url')
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
