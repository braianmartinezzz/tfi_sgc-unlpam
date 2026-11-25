import { agregarCola } from '@/lib/offline-storage';
import { NetworkError, apiRequest } from '@/services/api-client';

/**
 * Marca una notificación como leída. Si no hay conexión con el servidor queda en la cola
 * offline y se envía al volver la red (`sincronizarCola`); devuelve `false` en ese caso.
 */
export async function marcarNotificacionLeida(idNotificacion: number): Promise<boolean> {
  const path = `/notificaciones/${idNotificacion}/leida`;

  try {
    await apiRequest(path, { method: 'PATCH' });
    return true;
  } catch (error) {
    if (!(error instanceof NetworkError)) throw error;

    await agregarCola({ method: 'PATCH', path });
    return false;
  }
}

export async function marcarTodasNotificacionesLeidas(): Promise<number> {
  const respuesta = await apiRequest<{ cantidad: number }>('/notificaciones/leidas', {
    method: 'PATCH',
  });

  return respuesta.cantidad;
}
