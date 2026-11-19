import { apiRequest } from '@/services/api-client';
import type {
  ConvenioDetalle,
  FiltrosConvenios,
  PaginaConvenios,
  UnidadAcademica,
} from '@/types/convenio';

export async function listarConvenios(filtros: FiltrosConvenios = {}): Promise<PaginaConvenios> {
  const params = new URLSearchParams();

  if (filtros.buscar) params.set('buscar', filtros.buscar);
  if (filtros.estado) params.set('estado', filtros.estado);
  if (filtros.es_marco !== undefined) params.set('es_marco', String(filtros.es_marco));
  if (filtros.id_unidad_academica !== undefined) {
    params.set('id_unidad_academica', String(filtros.id_unidad_academica));
  }
  if (filtros.page && filtros.page > 1) params.set('page', String(filtros.page));

  const query = params.toString();
  return apiRequest<PaginaConvenios>(`/convenios${query ? `?${query}` : ''}`);
}

export async function listarUnidades(): Promise<UnidadAcademica[]> {
  const respuesta = await apiRequest<{ data: UnidadAcademica[] }>('/unidades-academicas');
  return respuesta.data;
}

/** Acepta tanto `{ data: {...} }` (API Resource) como el objeto directo. */
export async function obtenerConvenio(idInstrumento: number): Promise<ConvenioDetalle> {
  const respuesta = await apiRequest<ConvenioDetalle | { data: ConvenioDetalle }>(
    `/convenios/${idInstrumento}`,
  );

  return 'data' in respuesta ? respuesta.data : respuesta;
}
