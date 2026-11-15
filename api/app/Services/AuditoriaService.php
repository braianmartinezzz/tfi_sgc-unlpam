<?php

namespace App\Services;

use App\Models\RegistroAuditoria;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditoriaService
{
    public const POR_PAGINA = 20;

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = RegistroAuditoria::query()
            ->with([
                'usuario:id_usuario,nombre,mail',
                'instrumento:id_instrumento,titulo',
            ])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_registro');

        if (isset($filters['id_usuario']) && $filters['id_usuario'] !== '') {
            $query->where('id_usuario', $filters['id_usuario']);
        }

        if (isset($filters['id_instrumento']) && $filters['id_instrumento'] !== '') {
            $query->where('id_instrumento', $filters['id_instrumento']);
        }

        if (isset($filters['accion']) && $filters['accion'] !== '') {
            $query->where('accion', $filters['accion']);
        }

        if (isset($filters['buscar']) && $filters['buscar'] !== '') {
            $texto = '%'.$filters['buscar'].'%';

            $query->where(function ($subquery) use ($texto) {
                $subquery->where('accion', 'like', $texto)
                    ->orWhere('detalle', 'like', $texto);
            });
        }

        if (isset($filters['desde']) && $filters['desde'] !== '') {
            $query->whereDate('fecha_hora', '>=', $filters['desde']);
        }

        if (isset($filters['hasta']) && $filters['hasta'] !== '') {
            $query->whereDate('fecha_hora', '<=', $filters['hasta']);
        }

        $porPagina = (int) ($filters['per_page'] ?? self::POR_PAGINA);

        return $query->paginate($porPagina)->withQueryString();
    }

    /**
     * Registra una acción en el log. Pensado para que lo usen los demás servicios
     * (login, alta/edición/baja de convenios, usuarios, unidades, etc.).
     */
    public function registrar(
        string $accion,
        int $idUsuario,
        ?int $idInstrumento = null,
        ?string $detalle = null
    ): RegistroAuditoria {
        return RegistroAuditoria::query()->create([
            'accion' => $accion,
            'detalle' => $detalle,
            'id_instrumento' => $idInstrumento,
            'id_usuario' => $idUsuario,
        ]);
    }
}
