import { clearToken } from '@/lib/auth-storage';
import { limpiarOffline } from '@/lib/offline-storage';
import { apiRequest } from '@/services/api-client';
import { eliminarPushToken } from '@/services/push-token';
import type { UsuarioAutenticado } from '@/types/auth';

export async function obtenerUsuarioActual(): Promise<UsuarioAutenticado> {
  const respuesta = await apiRequest<{ user: UsuarioAutenticado }>('/me');
  return respuesta.user;
}

/** Revoca el token en el servidor (si hay conexión) y lo borra del dispositivo. */
export async function cerrarSesion(): Promise<void> {
  try {
    await eliminarPushToken();
  } catch {
    // El servidor puede no estar disponible: el cierre local debe completarse.
  }

  try {
    await apiRequest('/logout', { method: 'POST' });
  } catch {
    // Sin conexión o token ya vencido: igual se cierra la sesión local.
  }

  await clearToken();

  // Al cerrar sesión por decisión del usuario no queda su panel ni su cola en el celular.
  try {
    await limpiarOffline();
  } catch (error) {
    console.warn('No se pudieron borrar los datos offline:', error);
  }
}
