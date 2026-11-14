import { apiRequest } from "@/services/api-client";
import type { ApiMessageResponse, Paginated } from "@/types/api";
import type { Usuario, UsuarioResponse, UsuarioWrite } from "@/types/usuario";

export interface UsuarioFilters {
  id_rol?: number;
  id_unidad?: number;
  activo?: boolean;
  buscar?: string;
  page?: number;
}

function buildQuery(filters: UsuarioFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function list(
  filters: UsuarioFilters = {},
): Promise<Paginated<Usuario>> {
  return apiRequest<Paginated<Usuario>>(`/usuarios${buildQuery(filters)}`);
}

export async function get(id: number | string): Promise<Usuario> {
  const response = await apiRequest<UsuarioResponse>(`/usuarios/${id}`);
  return response.data;
}

export function create(input: UsuarioWrite): Promise<UsuarioResponse> {
  return apiRequest<UsuarioResponse>("/usuarios", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function update(
  id: number | string,
  input: Partial<UsuarioWrite>,
): Promise<UsuarioResponse> {
  return apiRequest<UsuarioResponse>(`/usuarios/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export function remove(id: number | string): Promise<ApiMessageResponse> {
  return apiRequest<ApiMessageResponse>(`/usuarios/${id}`, {
    method: "DELETE",
  });
}
