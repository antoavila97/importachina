<?php

namespace App\Http\Requests;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Filtros comunes a los cinco reportes. Los campos que no aplican a una
 * pestaña simplemente llegan vacíos y se ignoran.
 */
class ReportRequest extends SalesReportRequest
{
    /** Pestañas disponibles: clave => etiqueta. */
    public const TYPES = [
        'ventas' => 'Ventas',
        'pedidos' => 'Pedidos',
        'productos' => 'Productos',
        'categorias' => 'Categorías',
        'usuarios' => 'Usuarios',
    ];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'tipo' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'estado' => ['nullable', Rule::in(Order::STATUSES)],
            'categoria' => ['nullable', 'integer', 'exists:categories,id'],
            'activo' => ['nullable', Rule::in(['todos', '1', '0'])],
            'stock' => ['nullable', Rule::in(['todos', 'con', 'sin'])],
            'rol' => ['nullable', 'integer', 'exists:roles,id'],
            'situacion' => ['nullable', Rule::in(User::STATUSES)],
        ]);
    }

    public function tipo(): string
    {
        return $this->validated()['tipo'] ?? 'ventas';
    }

    public function estado(): ?string
    {
        return $this->validated()['estado'] ?? null;
    }

    public function categoriaId(): ?int
    {
        $value = $this->validated()['categoria'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function activo(): string
    {
        return $this->validated()['activo'] ?? 'todos';
    }

    public function stock(): string
    {
        return $this->validated()['stock'] ?? 'todos';
    }

    public function rolId(): ?int
    {
        $value = $this->validated()['rol'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function situacion(): ?string
    {
        return $this->validated()['situacion'] ?? null;
    }
}
