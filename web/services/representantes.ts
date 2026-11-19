import { apiRequest } from "@/services/api-client";
import type { CatalogoEstadoResponse, Paginated } from "@/types/api";
import type {
  Representante,
  RepresentanteResponse,
  RepresentanteWrite,
} from "@/types/representante";

export interface RepresentanteFilters {
  id_institucion?: number;
  buscar?: string;
  activo?: boolean;
  page?: number;
}

function buildQuery(filters: RepresentanteFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function list(
  filters: RepresentanteFilters = {},
): Promise<Paginated<Representante>> {
  return apiRequest<Paginated<Representante>>(
    `/representantes${buildQuery(filters)}`,
  );
}

export async function get(id: number | string): Promise<Representante> {
  const response = await apiRequest<RepresentanteResponse>(
    `/representantes/${id}`,
  );
  return response.data;
}

export function create(input: RepresentanteWrite): Promise<RepresentanteResponse> {
  return apiRequest<RepresentanteResponse>("/representantes", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function update(
  id: number | string,
  input: Partial<RepresentanteWrite>,
): Promise<RepresentanteResponse> {
  return apiRequest<RepresentanteResponse>(`/representantes/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export function setActivo(
  id: number | string,
  activo: boolean,
): Promise<CatalogoEstadoResponse<Representante>> {
  return apiRequest<CatalogoEstadoResponse<Representante>>(
    `/representantes/${id}/estado`,
    {
      method: "PATCH",
      body: JSON.stringify({ activo }),
    },
  );
}
