import { clearToken } from '@/lib/auth-storage';
import { apiRequest } from '@/services/api-client';
import type { UsuarioAutenticado } from '@/types/auth';

export async function obtenerUsuarioActual(): Promise<UsuarioAutenticado> {
  const respuesta = await apiRequest<{ user: UsuarioAutenticado }>('/me');
  return respuesta.user;
}

/** Revoca el token en el servidor (si hay conexión) y lo borra del dispositivo. */
export async function cerrarSesion(): Promise<void> {
  try {
    await apiRequest('/logout', { method: 'POST' });
  } catch {
    // Sin conexión o token ya vencido: igual se cierra la sesión local.
  }

  await clearToken();
}
