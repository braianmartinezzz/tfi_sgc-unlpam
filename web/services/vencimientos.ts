import { apiRequest } from "@/services/api-client";

export interface Vencimiento {
  id_instrumento: number;
  titulo: string;
  estado: string;
  tipo: string;
  vencimiento: string;
  dias_restantes: number;
  dias_preaviso: number | null;
  en_preaviso: boolean;
  unidad_academica: string | null;
  instituciones_firmantes: string[];
}

export interface VencimientoFilters {
  dias?: number;
  estado?: string;
  id_unidad_academica?: number;
}

export interface VencimientosResponse {
  data: Vencimiento[];
  meta: {
    dias: number;
    desde: string;
    hasta: string;
    total: number;
  };
}

function buildQuery(filters: VencimientoFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined) params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function list(
  filters: VencimientoFilters = {},
): Promise<VencimientosResponse> {
  return apiRequest<VencimientosResponse>(
    `/convenios/vencimientos${buildQuery(filters)}`,
  );
}
