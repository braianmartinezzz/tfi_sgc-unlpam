import { API_URL } from '@/constants/api';
import { getToken } from '@/lib/auth-storage';

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

    throw new ApiError(response.status, message);
  }

  return (await response.json()) as T;
}
