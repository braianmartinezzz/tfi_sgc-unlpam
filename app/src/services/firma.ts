import { File, Paths } from 'expo-file-system';
import { Platform } from 'react-native';

import { API_URL } from '@/constants/api';
import { getToken } from '@/lib/auth-storage';
import type { PendienteFirmaCompleto } from '@/types/firma';
import type { ComentarioFirmador } from '@/types/firmador-comentarios';
import { apiRequest, ApiError } from '@/services/api-client';

export async function listarPendientesFirma(): Promise<PendienteFirmaCompleto[]> {
  const respuesta = await apiRequest<{ data: PendienteFirmaCompleto[] }>('/firmas/pendientes');
  return respuesta.data;
}

/**
 * Un PDF ya bajado de la API: en la web queda como Blob (se descarga desde el navegador);
 * en Android e iOS se guarda en la caché de la app y se devuelve su uri para compartirlo.
 */
export type DocumentoDescargado =
  | { tipo: 'web'; blob: Blob }
  | { tipo: 'archivo'; uri: string };

/** Nombre apto para el sistema de archivos del celular, siempre con extensión .pdf. */
function nombreDePdf(nombre: string): string {
  const limpio = nombre
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^\w.-]+/g, '_').replace(/^_+|_+$/g, '') || 'documento';
  return limpio.toLowerCase().endsWith('.pdf') ? limpio : `${limpio}.pdf`;
}

async function descargarPdf(
  ruta: string,
  nombreArchivo: string,
  mensajePorDefecto: string,
): Promise<DocumentoDescargado> {
  const token = await getToken();
  if (!token) throw new ApiError(401, 'Iniciá sesión nuevamente para descargar el documento.');

  const response = await fetch(`${API_URL}/api${ruta}`, {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/pdf' },
  });

  if (!response.ok) {
    let mensaje = mensajePorDefecto;
    try {
      const body = await response.json();
      if (typeof body?.message === 'string') mensaje = body.message;
    } catch {
      // Se conserva el mensaje genérico.
    }
    throw new ApiError(response.status, mensaje);
  }

  if (Platform.OS === 'web') return { tipo: 'web', blob: await response.blob() };

  const archivo = new File(Paths.cache, nombreDePdf(nombreArchivo));
  archivo.create({ overwrite: true });
  archivo.write(new Uint8Array(await response.arrayBuffer()));

  return { tipo: 'archivo', uri: archivo.uri };
}

export function descargarDocumentoFirma(
  idInstrumento: number,
  nombreArchivo: string,
): Promise<DocumentoDescargado> {
  return descargarPdf(
    `/firmas/${idInstrumento}/documento`,
    nombreArchivo,
    'No se pudo descargar el documento.',
  );
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

export function descargarConstanciaFirma(idInstrumento: number): Promise<DocumentoDescargado> {
  return descargarPdf(
    `/convenios/${idInstrumento}/constancia-firma`,
    `constancia-firma-${idInstrumento}.pdf`,
    'No se pudo generar la constancia PDF.',
  );
}
