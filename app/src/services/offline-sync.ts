import NetInfo from '@react-native-community/netinfo';

import {
  leerCola,
  quitarDeCola,
  actualizarCola,
  type PendingOperation,
} from '@/lib/offline-storage';
import { apiRequest } from '@/services/api-client';

let sincronizando = false;

async function hayConexion(): Promise<boolean> {
  const estado = await NetInfo.fetch();
  return estado.isConnected === true && estado.isInternetReachable !== false;
}

export async function sincronizarCola(): Promise<{ procesadas: number; pendientes: number }> {
  if (sincronizando) return { procesadas: 0, pendientes: await contarPendientes() };
  if (!(await hayConexion())) return { procesadas: 0, pendientes: await contarPendientes() };

  sincronizando = true;

  try {
    let cola = await leerCola();
    let procesadas = 0;

    for (const operacion of cola) {
      try {
        await apiRequest(operacion.path, {
          method: operacion.method,
          ...(operacion.body !== undefined
            ? { body: JSON.stringify(operacion.body) }
            : {}),
        });

        await quitarDeCola(operacion.id);
        procesadas++;
      } catch (error) {
        const actualizado: PendingOperation = {
          ...operacion,
          attempts: operacion.attempts + 1,
        };

        cola = cola.map((item) => (item.id === operacion.id ? actualizado : item));
        await actualizarCola(cola);

        if (error instanceof Error) break;
      }
    }

    const pendientes = await contarPendientes();
    return { procesadas, pendientes };
  } finally {
    sincronizando = false;
  }
}

export async function contarPendientes(): Promise<number> {
  return (await leerCola()).length;
}
