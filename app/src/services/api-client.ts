import { router } from 'expo-router';

import { API_URL } from '@/constants/api';
import { clearToken, getToken } from '@/lib/auth-storage';
import { marcarSesionVencida } from '@/lib/sesion-estado';

const TIMEOUT_MS = 15000;

/** La API respondió con un error (401, 403, 422, 500...). */
export class ApiError extends Error {
  status: number;

  constructor(status: number, message: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

/** No se pudo llegar al servidor (sin conexión, timeout, API apagada). */
export class NetworkError extends Error {
  constructor(message = 'No se pudo conectar con el servidor.') {
    super(message);
    this.name = 'NetworkError';
  }
}

let cerrandoSesion = false;

/** Token vencido o revocado: se limpia la sesión y se vuelve al login, una sola vez. */
async function sesionExpirada(): Promise<void> {
  if (cerrandoSesion) return;
  cerrandoSesion = true;

  try {
    marcarSesionVencida();
    await clearToken();
    router.replace('/');
  } catch {
    // Si el router todavía no está listo no hay a dónde redirigir; el error igual se propaga.
  } finally {
    setTimeout(() => {
      cerrandoSesion = false;
    }, 1000);
  }
}

export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = await getToken();
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), TIMEOUT_MS);

  let response: Response;

  try {
    response = await fetch(`${API_URL}/api${path}`, {
      ...init,
      signal: controller.signal,
      headers: {
        Accept: 'application/json',
        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...init.headers,
      },
    });
  } catch {
    throw new NetworkError();
  } finally {
    clearTimeout(timeoutId);
  }

  if (!response.ok) {
    let message = `Error ${response.status}`;

    try {
      const body = await response.json();
      if (typeof body?.message === 'string') message = body.message;
    } catch {
      // La respuesta no era JSON: se conserva el mensaje genérico.
    }

    // Se ignora el 401 del propio cierre de sesión (el token ya no importa).
    if (response.status === 401 && token && path !== '/logout') void sesionExpirada();

    throw new ApiError(response.status, message);
  }

  return (await response.json()) as T;
}
