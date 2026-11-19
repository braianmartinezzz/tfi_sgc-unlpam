import { apiRequest } from "@/services/api-client";
import type { Rol } from "@/types/auth";

interface RolListResponse {
  data: Rol[];
}

export async function list(): Promise<Rol[]> {
  const response = await apiRequest<RolListResponse>("/roles");
  return response.data;
}
