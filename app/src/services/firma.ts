import { API_URL } from '@/constants/api';
import { getToken } from '@/lib/auth-storage';
import type { PendienteFirmaCompleto } from '@/types/firma';
import { apiRequest, ApiError } from '@/services/api-client';

export async function listarPendientesFirma(): Promise<PendienteFirmaCompleto[]> {
  const respuesta = await apiRequest<{ data: PendienteFirmaCompleto[] }>('/firmas/pendientes');
  return respuesta.data;
}

export async function descargarDocumentoFirma(idInstrumento: number): Promise<Response> {
  const token = await getToken();
  const response = await fetch(`${API_URL}/api/firmas/${idInstrumento}/documento`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });

  if (!response.ok) {
    let message = 'No se pudo descargar el documento.';
    try {
      const body = await response.json();
      if (typeof body?.message === 'string') message = body.message;
    } catch {
      // Se conserva el mensaje genérico.
    }
    throw new ApiError(response.status, message);
  }

  return response;
}

export async function registrarFirma(
  idInstrumento: number,
  datos: { trazo: string; latitud: number; longitud: number; biometria_confirmada: boolean },
): Promise<{ estado: string; firmas_pendientes: number }> {
  const respuesta = await apiRequest<{
    data: { estado: string; firmas_pendientes: number };
  }>(`/convenios/${idInstrumento}/firmar`, {
    method: 'POST',
    body: JSON.stringify(datos),
  });

  return respuesta.data;
}
