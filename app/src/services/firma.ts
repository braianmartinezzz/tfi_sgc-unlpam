import { API_URL } from '@/constants/api';
import { getToken } from '@/lib/auth-storage';
import type { PendienteFirmaCompleto } from '@/types/firma';
import type { ComentarioFirmador } from '@/types/firmador-comentarios';
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

export async function listarComentariosFirma(idInstrumento: number): Promise<ComentarioFirmador[]> {
  const respuesta = await apiRequest<{ data: ComentarioFirmador[] }>(`/convenios/${idInstrumento}/comentarios-firmador`);
  return respuesta.data;
}

export async function crearComentarioFirma(idInstrumento: number, contenido: string): Promise<ComentarioFirmador & { ofensivo: boolean }> {
  const respuesta = await apiRequest<{ data: ComentarioFirmador }>(`/convenios/${idInstrumento}/comentarios-firmador`, {
    method: 'POST',
    body: JSON.stringify({ contenido }),
  });
  return respuesta.data as ComentarioFirmador & { ofensivo: boolean };
}

export async function descargarConstanciaFirma(idInstrumento: number): Promise<DocumentoDescargado> {
  const token = await getToken();
  if (!token) throw new ApiError(401, 'Iniciá sesión nuevamente para descargar la constancia.');

  const response = await fetch(`${API_URL}/api/convenios/${idInstrumento}/constancia-firma`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf' },
  });
  if (!response.ok) throw new ApiError(response.status, 'No se pudo generar la constancia PDF.');

  if (Platform.OS === 'web') return { tipo: 'web', blob: await response.blob() };
  const archivo = new File(Paths.cache, `constancia-firma-${idInstrumento}.pdf`);
  await archivo.write(await response.arrayBuffer());
  return { tipo: 'archivo', uri: archivo.uri };
}
