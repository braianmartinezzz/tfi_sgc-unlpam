import { apiRequest } from "@/services/api-client";
import type { CatalogoEstadoResponse } from "@/types/api";
import type {
  UnidadAcademica,
  UnidadAcademicaListResponse,
  UnidadAcademicaResponse,
  UnidadAcademicaWrite,
} from "@/types/unidad";

export interface UnidadFilters {
  id_institucion?: number;
  buscar?: string;
  activo?: boolean;
}

function buildQuery(filters: UnidadFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export async function list(
  filters: UnidadFilters = {},
): Promise<UnidadAcademica[]> {
  const response = await apiRequest<UnidadAcademicaListResponse>(
    `/unidades-academicas${buildQuery(filters)}`,
  );
  return response.data;
}

export async function get(
  id: number | string,
): Promise<UnidadAcademica> {
  const response = await apiRequest<UnidadAcademicaResponse>(
    `/unidades-academicas/${id}`,
  );
  return response.data;
}

export function create(
  input: UnidadAcademicaWrite,
): Promise<UnidadAcademicaResponse> {
  return apiRequest<UnidadAcademicaResponse>("/unidades-academicas", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function update(
  id: number | string,
  input: Partial<UnidadAcademicaWrite>,
): Promise<UnidadAcademicaResponse> {
  return apiRequest<UnidadAcademicaResponse>(`/unidades-academicas/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export function setActivo(
  id: number | string,
  activo: boolean,
): Promise<CatalogoEstadoResponse<UnidadAcademica>> {
  return apiRequest<CatalogoEstadoResponse<UnidadAcademica>>(
    `/unidades-academicas/${id}/estado`,
    {
      method: "PATCH",
      body: JSON.stringify({ activo }),
    },
  );
}
