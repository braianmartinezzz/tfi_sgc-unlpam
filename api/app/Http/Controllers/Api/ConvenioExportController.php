<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConvenioExportRequest;
use App\Services\ConvenioExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConvenioExportController extends Controller
{
    private const SEPARADOR = ';'; // Excel en configuración regional argentina abre bien con ';'

    public function __construct(private ConvenioExportService $service)
    {
    }

    public function index(ConvenioExportRequest $request): StreamedResponse
    {
        $filtros = $request->validated();
        if ($request->has('es_marco')) {
            $filtros['es_marco'] = $request->boolean('es_marco');
        }

        $consulta = $this->service->consulta($filtros);
        $nombre = 'convenios_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($consulta) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para que Excel respete los acentos
            fputcsv($out, ConvenioExportService::ENCABEZADOS, self::SEPARADOR);

            foreach ($consulta->cursor() as $fila) {
                fputcsv($out, array_map(
                    fn ($valor) => $this->sanitizar($valor),
                    array_values((array) $fila)
                ), self::SEPARADOR);
            }

            fclose($out);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // Evita que Excel interprete una celda como fórmula (CSV injection).
    private function sanitizar(mixed $valor): mixed
    {
        if (is_string($valor) && $valor !== '' && in_array($valor[0], ['=', '+', '-', '@'], true)) {
            return "'" . $valor;
        }

        return $valor;
    }
}