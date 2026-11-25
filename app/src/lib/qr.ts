import { API_URL } from '@/constants/api';

/**
 * Formatos de QR aceptados (definición del formato de la Fase 4):
 *
 * 1. Canónico de la app:  `sgc:convenio:<id>`
 * 2. URL de la API:       `http(s)://<host-api>/convenios/<id>`  (o `/convenio/<id>`)
 *    Solo se acepta si el origin coincide exactamente con el de `API_URL`:
 *    un código de otro sistema se rechaza antes de llamar a la API.
 * 3. ID numérico plano:   `<id>`  (se valida contra la API al buscarlo).
 */
export type ResultadoQr =
  | { ok: true; id: number }
  /** No es un QR de convenios: formato desconocido. */
  | { ok: false; motivo: 'formato' }
  /** Es una URL, pero no pertenece a esta API. */
  | { ok: false; motivo: 'origen' };

const PREFIJO = /^sgc:convenio:(\d+)$/i;
const ID_PLANO = /^\d+$/;

function idValido(texto: string): number {
  const id = Number(texto);
  return Number.isInteger(id) && id > 0 ? id : 0;
}

/** Extrae y valida el identificador de convenio de un contenido QR. */
export function parsearQrConvenio(contenido: string): ResultadoQr {
  const texto = contenido.trim();
  if (!texto) return { ok: false, motivo: 'formato' };

  if (ID_PLANO.test(texto)) {
    const id = idValido(texto);
    return id ? { ok: true, id } : { ok: false, motivo: 'formato' };
  }

  const prefijo = texto.match(PREFIJO);
  if (prefijo) {
    const id = idValido(prefijo[1]);
    return id ? { ok: true, id } : { ok: false, motivo: 'formato' };
  }

  if (/^https?:\/\//i.test(texto)) {
    let url: URL;
    try {
      url = new URL(texto);
    } catch {
      return { ok: false, motivo: 'formato' };
    }

    let origenApi: URL;
    try {
      origenApi = new URL(API_URL);
    } catch {
      return { ok: false, motivo: 'formato' };
    }

    // Mismo sistema: protocolo + host (con puerto) deben coincidir.
    if (url.origin !== origenApi.origin) return { ok: false, motivo: 'origen' };

    const coincidencia = url.pathname.match(/\/(?:convenios|convenio)\/(\d+)/i);
    if (!coincidencia) return { ok: false, motivo: 'formato' };

    const id = idValido(coincidencia[1]);
    return id ? { ok: true, id } : { ok: false, motivo: 'formato' };
  }

  return { ok: false, motivo: 'formato' };
}
