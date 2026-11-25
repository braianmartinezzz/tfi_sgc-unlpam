import type { Href } from 'expo-router';

/**
 * Pantalla a la que ir después de iniciar sesión: se guarda cuando el usuario toca un aviso
 * (push) pero la sesión ya había vencido. Vive en memoria y caduca a los pocos minutos.
 */
const VIGENCIA_MS = 5 * 60 * 1000;

let pendiente: { destino: Href; guardadoEn: number } | null = null;

export function guardarDestinoPendiente(destino: Href, ahora = Date.now()): void {
  pendiente = { destino, guardadoEn: ahora };
}

/** Devuelve el destino una sola vez (o null si no hay o caducó). */
export function consumirDestinoPendiente(ahora = Date.now()): Href | null {
  const actual = pendiente;
  pendiente = null;

  return actual && ahora - actual.guardadoEn <= VIGENCIA_MS ? actual.destino : null;
}
