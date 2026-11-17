import { apiRequest } from "@/services/api-client";
import type { Paginated } from "@/types/api";
import type { InstitucionFirmante } from "@/types/unidad";

export interface InstitucionFirmanteFilters {
  buscar?: string;
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

export async function listAll(
  buscar?: string,
): Promise<InstitucionFirmante[]> {
  const institutions: InstitucionFirmante[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await list({ buscar, page });
    institutions.push(...response.data);
    lastPage = response.last_page;
    page += 1;
  } while (page <= lastPage);

  return institutions;
}
