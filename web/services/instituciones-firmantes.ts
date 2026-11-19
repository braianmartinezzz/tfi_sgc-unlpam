import { apiRequest } from "@/services/api-client";
import type { CatalogoEstadoResponse, Paginated } from "@/types/api";
import type {
  InstitucionFirmante,
  InstitucionFirmanteResponse,
  InstitucionFirmanteWrite,
} from "@/types/unidad";

export interface InstitucionFirmanteFilters {
  buscar?: string;
  tipo_institucion?: string;
  activo?: boolean;
  page?: number;
}

function buildQuery(filters: InstitucionFirmanteFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function list(
  filters: InstitucionFirmanteFilters = {},
): Promise<Paginated<InstitucionFirmante>> {
  return apiRequest<Paginated<InstitucionFirmante>>(
    `/instituciones-firmantes${buildQuery(filters)}`,
  );
}

export async function get(
  id: number | string,
): Promise<InstitucionFirmante> {
  const response = await apiRequest<InstitucionFirmanteResponse>(
    `/instituciones-firmantes/${id}`,
  );
  return response.data;
}

export function create(
  input: InstitucionFirmanteWrite,
): Promise<InstitucionFirmanteResponse> {
  return apiRequest<InstitucionFirmanteResponse>("/instituciones-firmantes", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function update(
  id: number | string,
  input: Partial<InstitucionFirmanteWrite>,
): Promise<InstitucionFirmanteResponse> {
  return apiRequest<InstitucionFirmanteResponse>(
    `/instituciones-firmantes/${id}`,
    {
      method: "PUT",
      body: JSON.stringify(input),
    },
  );
}

export function setActivo(
  id: number | string,
  activo: boolean,
): Promise<CatalogoEstadoResponse<InstitucionFirmante>> {
  return apiRequest<CatalogoEstadoResponse<InstitucionFirmante>>(
    `/instituciones-firmantes/${id}/estado`,
    {
      method: "PATCH",
      body: JSON.stringify({ activo }),
    },
  );
}

export async function listAll(
  buscar?: string,
  activo?: boolean,
): Promise<InstitucionFirmante[]> {
  const institutions: InstitucionFirmante[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await list({ buscar, activo, page });
    institutions.push(...response.data);
    lastPage = response.last_page;
    page += 1;
  } while (page <= lastPage);

  return institutions;
}
