import { guardarOffline, leerOffline } from '@/lib/offline-storage';
import type { MovimientoPanel } from '@/types/panel';

/** Un movimiento que el usuario abrió desde el panel o la pantalla de movimientos. */
export type MovimientoConsultado = MovimientoPanel & {
  /** Cuándo se abrió por última vez (para ordenar el historial). */
  registrado_el: string;
};

const CLAVE = 'movimientos_consultados';
/** Cuántos movimientos consultados se conservan. */
const MAXIMO = 20;

/**
 * Historial local (AsyncStorage) de movimientos que el usuario fue consultando.
 * Sobrevive al cierre de la app y permite navegar desde ellos sin conexión.
 */
export async function leerMovimientosConsultados(): Promise<MovimientoConsultado[]> {
  return (await leerOffline<MovimientoConsultado[]>(CLAVE)) ?? [];
}

/**
 * Registra un movimiento como consultado: lo mueve al principio del historial
 * (dedupe por convenio) y recorta la lista a los `MAXIMO` más recientes.
 */
export async function registrarMovimientoConsultado(
  movimiento: MovimientoPanel,
): Promise<void> {
  const historial = await leerMovimientosConsultados();
  const sinEste = historial.filter((m) => m.id_instrumento !== movimiento.id_instrumento);

  const nuevo: MovimientoConsultado = {
    ...movimiento,
    registrado_el: new Date().toISOString(),
  };

  await guardarOffline(CLAVE, [nuevo, ...sinEste].slice(0, MAXIMO));
}
