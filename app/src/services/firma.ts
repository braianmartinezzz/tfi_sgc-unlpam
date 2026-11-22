import { Platform } from 'react-native';
import { File, Paths } from 'expo-file-system';
import { API_URL } from '@/constants/api';
import { getToken } from '@/lib/auth-storage';
import type { PendienteFirmaCompleto } from '@/types/firma';
import { apiRequest, ApiError } from '@/services/api-client';

export type DocumentoDescargado =
  | { tipo: 'web'; blob: Blob }
  | { tipo: 'archivo'; uri: string };

export async function listarPendientesFirma(): Promise<PendienteFirmaCompleto[]> {
  const respuesta = await apiRequest<{ data: PendienteFirmaCompleto[] }>('/firmas/pendientes');
  return respuesta.data;
}

export async function descargarDocumentoFirma(
  idInstrumento: number,
  nombreArchivo: string,
): Promise<DocumentoDescargado> {
  const token = await getToken();
  if (!token) throw new ApiError(401, 'Iniciá sesión nuevamente para descargar el documento.');

  let response: Response;
  try {
    response = await fetch(`${API_URL}/api/firmas/${idInstrumento}/documento`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf' },
    });
  } catch {
    throw new Error('No se pudo conectar con el servidor para descargar el documento.');
  }

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

  const contentType = response.headers.get('Content-Type')?.split(';', 1)[0].trim().toLowerCase();
  if (contentType !== 'application/pdf') {
    throw new Error('El servidor no devolvió un documento PDF válido.');
  }

  if (Platform.OS === 'web') {
    return { tipo: 'web', blob: await response.blob() };
  }

  const archivo = new File(Paths.cache, nombreArchivo);
  await archivo.write(await response.arrayBuffer());

  return { tipo: 'archivo', uri: archivo.uri };
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
