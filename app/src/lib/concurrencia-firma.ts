import type { PendienteFirmaCompleto } from '@/types/firma';

export type MotivoConflicto = 'modificado' | 'documento' | 'no-disponible';

export type ConflictoFirma = {
  motivo: MotivoConflicto;
  /** Estado vigente en el servidor; null si el convenio ya no está pendiente de firma. */
  actual: PendienteFirmaCompleto | null;
  /** Qué cambió, listo para mostrar. */
  cambios: string[];
  mensaje: string;
};

const MENSAJES: Record<MotivoConflicto, string> = {
  modificado: 'El convenio fue modificado por otra persona desde que lo abriste.',
  documento: 'El documento a firmar cambió desde que lo abriste.',
  'no-disponible':
    'Este convenio ya no está pendiente de tu firma (cambió de estado o ya fue firmado).',
};

/**
 * Compara lo que la persona tiene abierto con lo que hay hoy en el servidor.
 * Devuelve null si no hubo cambios y se puede firmar sin riesgo.
 *
 * Además de `version_registro` se compara el documento: la API no suma versión al
 * reemplazar un PDF, y firmar sobre un PDF distinto del que se leyó es el caso más grave.
 */
export function detectarConflicto(
  local: PendienteFirmaCompleto,
  actual: PendienteFirmaCompleto | undefined,
  mensajeServidor?: string,
): ConflictoFirma | null {
  if (!actual) {
    return {
      motivo: 'no-disponible',
      actual: null,
      cambios: [],
      mensaje: mensajeServidor ?? MENSAJES['no-disponible'],
    };
  }

  const cambios: string[] = [];

  if (actual.version_registro !== local.version_registro) {
    cambios.push(`Versión del convenio: ${local.version_registro} → ${actual.version_registro}`);
  }

  const documentoCambio =
    local.documento?.id_documento !== actual.documento?.id_documento ||
    local.documento?.id_version !== actual.documento?.id_version;

  if (documentoCambio) {
    cambios.push(
      actual.documento
        ? `Documento: ahora es "${actual.documento.nombre_archivo}"`
        : 'El convenio ya no tiene un documento listo para firmar',
    );
  }

  if (cambios.length === 0) return null;

  const motivo: MotivoConflicto = documentoCambio ? 'documento' : 'modificado';

  return { motivo, actual, cambios, mensaje: MENSAJES[motivo] };
}
