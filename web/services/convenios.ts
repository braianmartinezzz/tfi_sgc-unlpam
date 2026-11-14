import { apiDownload, apiRequest } from "@/services/api-client";
import type { ApiMessageResponse, Paginated } from "@/types/api";
import type {
  Convenio,
  ConvenioCreateInput,
  ConvenioListFilters,
  ConvenioMutationResponse,
  ConvenioResponse,
  ConvenioUpdateInput,
} from "@/types/convenio";

function buildQuery(filters: ConvenioListFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function list(
  filters: ConvenioListFilters = {},
): Promise<Paginated<Convenio>> {
  return apiRequest<Paginated<Convenio>>(
    `/convenios${buildQuery(filters)}`,
  );
}

export async function get(id: number | string): Promise<Convenio> {
  const response = await apiRequest<ConvenioResponse>(`/convenios/${id}`);
  return response.data;
}

export function create(
  input: ConvenioCreateInput,
): Promise<ConvenioMutationResponse> {
  return apiRequest<ConvenioMutationResponse>("/convenios", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function update(
  id: number | string,
  input: ConvenioUpdateInput,
): Promise<ConvenioMutationResponse> {
  return apiRequest<ConvenioMutationResponse>(`/convenios/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export function remove(
  id: number | string,
): Promise<ApiMessageResponse> {
  return apiRequest<ApiMessageResponse>(`/convenios/${id}`, {
    method: "DELETE",
  });
}

export function exportar(
  filters: Omit<ConvenioListFilters, "page"> = {},
): Promise<void> {
  return apiDownload(`/convenios/exportar${buildQuery(filters)}`);
}
