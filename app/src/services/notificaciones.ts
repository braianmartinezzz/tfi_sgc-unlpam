import { apiRequest } from '@/services/api-client';

export async function marcarNotificacionLeida(idNotificacion: number): Promise<void> {
  await apiRequest(`/notificaciones/${idNotificacion}/leida`, { method: 'PATCH' });
}

export async function marcarTodasNotificacionesLeidas(): Promise<number> {
  const respuesta = await apiRequest<{ cantidad: number }>('/notificaciones/leidas', {
    method: 'PATCH',
  });

  return respuesta.cantidad;
}
