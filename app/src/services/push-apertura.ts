import { router } from 'expo-router';

import { guardarDestinoPendiente } from '@/lib/destino-pendiente';
import { destinoDePush } from '@/lib/push-destino';
import { ApiError, apiRequest } from '@/services/api-client';
import { getToken } from '@/lib/auth-storage';

/**
 * Abre la pantalla que corresponde al aviso que tocó el usuario.
 *
 * Antes de navegar se comprueba la sesión (`/me`, que además la renueva): con 10 minutos de
 * inactividad lo habitual es que haya vencido. En ese caso se vuelve al login y, al ingresar,
 * se abre el destino pedido. Sin conexión se navega igual (se muestran los datos guardados).
 */
export async function abrirDesdePush(data: Record<string, unknown>): Promise<void> {
  const destino = destinoDePush(data);
  if (destino === null) return;

  if (!(await getToken())) {
    guardarDestinoPendiente(destino);
    router.replace('/');
    return;
  }

  try {
    await apiRequest('/me');
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      // api-client ya limpió la sesión y llevó al login.
      guardarDestinoPendiente(destino);
      return;
    }
  }

  router.push(destino);
}
