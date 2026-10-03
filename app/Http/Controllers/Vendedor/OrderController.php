<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $orders = Order::query()
            ->with('user')
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['search'], function ($query, $search) {
                $query->whereHas('user', fn ($userQuery) => $userQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('vendedor.pedidos.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => Order::STATUSES,
            'counts' => $this->statusCounts(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.product', 'payments']);

        return view('vendedor.pedidos.show', [
            'order' => $order,
            'statuses' => Order::STATUSES,
            'paymentMethods' => PaymentController::METHODS,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        $status = $request->status();

        $order->update(['status' => $status]);

        return redirect()
            ->route('vendedor.orders.show', $order)
            ->with('success', "El pedido #{$order->id} quedo en estado {$order->fresh()->statusLabel()}.");
    }

    /**
     * @return array{status: ?string, from: ?string, to: ?string, search: ?string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', Order::STATUSES)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'status' => $validated['status'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'search' => $validated['search'] ?? null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $result = [];
        foreach (Order::STATUSES as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }
        $result['todos'] = (int) array_sum($result);

        return $result;
    }
}
