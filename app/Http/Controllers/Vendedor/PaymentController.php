<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterPaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public const METHODS = [
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia bancaria',
        'tarjeta' => 'Tarjeta',
        'qr' => 'QR / Pago móvil',
        'otro' => 'Otro',
    ];

    public function store(RegisterPaymentRequest $request, Order $order): RedirectResponse
    {
        $amount = $request->amount() ?? $order->total;
        $paidAt = $request->paidAt() ?? now();

        DB::transaction(function () use ($order, $request, $amount, $paidAt) {
            Payment::create([
                'order_id' => $order->id,
                'method' => $request->method(),
                'amount' => $amount,
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $paidAt,
            ]);

            $order->update(['status' => Order::STATUS_PAGADO]);
        });

        return redirect()
            ->route('vendedor.orders.show', $order)
            ->with('success', 'Pago registrado. El pedido quedo en estado Pagado.');
    }

    /**
     * Corrige un pago ya registrado (monto, metodo o fecha).
     */
    public function update(UpdatePaymentRequest $request, Order $order, Payment $payment): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id, 404);

        $payment->update([
            'method' => $request->method(),
            'amount' => $request->amount(),
            'paid_at' => $request->paidAt(),
        ]);

        return redirect()
            ->route('vendedor.orders.show', $order)
            ->with('success', 'Pago actualizado.');
    }

    /**
     * Anula un pago: no se borra, queda registrado como Anulado para conservar la trazabilidad.
     * Si era el unico pago vigente, el pedido vuelve a Pendiente.
     */
    public function void(Order $order, Payment $payment): RedirectResponse
    {
        abort_unless($payment->order_id === $order->id, 404);

        DB::transaction(function () use ($order, $payment) {
            $payment->update(['status' => Payment::STATUS_VOIDED]);

            $stillPaid = $order->payments()
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereKeyNot($payment->getKey())
                ->exists();

            if (! $stillPaid && $order->status === Order::STATUS_PAGADO) {
                $order->update(['status' => Order::STATUS_PENDIENTE]);
            }
        });

        return redirect()
            ->route('vendedor.orders.show', $order)
            ->with('success', 'Pago anulado.');
    }
}
