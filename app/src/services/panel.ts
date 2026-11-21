import { apiRequest } from '@/services/api-client';
import type { PanelData } from '@/types/panel';

export async function obtenerPanel(): Promise<PanelData> {
  const respuesta = await apiRequest<{ data: PanelData }>('/panel');
  return respuesta.data;
}
