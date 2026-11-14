import { apiRequest } from "@/services/api-client";
import type { EstadisticaResponse } from "@/types/estadistica";

export interface EstadisticaFilters {
  dias?: number;
  id_unidad_academica?: number;
}

function buildQuery(filters: EstadisticaFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined) params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function get(
  filters: EstadisticaFilters = {},
): Promise<EstadisticaResponse> {
  return apiRequest<EstadisticaResponse>(
    `/estadisticas${buildQuery(filters)}`,
  );
}
