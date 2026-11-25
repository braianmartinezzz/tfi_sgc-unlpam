import NetInfo from '@react-native-community/netinfo';

import {
  actualizarOperacion,
  leerCola,
  quitarDeCola,
} from '@/lib/offline-storage';
import { ApiError, NetworkError, apiRequest } from '@/services/api-client';

/** Reintentos de un mismo pedido que falla por el servidor (5xx, 408, 429) antes de descartarlo. */
export const MAX_INTENTOS = 5;

let sincronizando = false;

async function hayConexion(): Promise<boolean> {
  const estado = await NetInfo.fetch();
  return estado.isConnected === true && estado.isInternetReachable !== false;
}

/** Errores que no se arreglan reintentando (datos inválidos, sin permiso, ya no existe). */
function esErrorDefinitivo(error: unknown): boolean {
  return (
    error instanceof ApiError &&
    error.status >= 400 &&
    error.status < 500 &&
    error.status !== 401 &&
    error.status !== 408 &&
    error.status !== 429
  );
}

export type ResultadoSincronizacion = {
  procesadas: number;
  /** Operaciones que el servidor rechazó de forma definitiva y se sacaron de la cola. */
  descartadas: number;
  pendientes: number;
};

/**
 * Envía en orden lo que quedó en la cola mientras no había conexión.
 *
 * - Éxito: la operación sale de la cola.
 * - Sin conexión o sesión vencida (401): se corta y todo queda para después.
 * - Rechazo definitivo (400, 403, 404, 409, 422...): se descarta y sigue con la próxima,
 *   para que un pedido inválido no trabe a los demás.
 * - Falla del servidor (5xx, 408, 429): se cuenta el intento y se corta; al llegar a
 *   `MAX_INTENTOS` se descarta.
 */
export async function sincronizarCola(): Promise<ResultadoSincronizacion> {
  if (sincronizando || !(await hayConexion())) {
    return { procesadas: 0, descartadas: 0, pendientes: await contarPendientes() };
  }

  sincronizando = true;

  try {
    let procesadas = 0;
    let descartadas = 0;

    for (const operacion of await leerCola()) {
      try {
        await apiRequest(operacion.path, {
          method: operacion.method,
          ...(operacion.body !== undefined ? { body: JSON.stringify(operacion.body) } : {}),
        });

        await quitarDeCola(operacion.id);
        procesadas++;
      } catch (error) {
        if (error instanceof NetworkError || (error instanceof ApiError && error.status === 401)) break;

        if (esErrorDefinitivo(error)) {
          await quitarDeCola(operacion.id);
          descartadas++;
          continue;
        }

        const intentos = operacion.attempts + 1;

        if (intentos >= MAX_INTENTOS) {
          await quitarDeCola(operacion.id);
          descartadas++;
        } else {
          await actualizarOperacion(operacion.id, { attempts: intentos });
        }

        break;
      }
    }

    return { procesadas, descartadas, pendientes: await contarPendientes() };
  } finally {
    sincronizando = false;
  }
}

export async function contarPendientes(): Promise<number> {
  return (await leerCola()).length;
}
