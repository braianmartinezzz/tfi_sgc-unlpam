import { router } from 'expo-router';

import { getToken } from '@/lib/auth-storage';
import { consumirSesionVencida } from '@/lib/sesion-estado';
import { ApiError, NetworkError, apiRequest } from '@/services/api-client';

jest.mock('@/lib/auth-storage', () => ({ getToken: jest.fn(), clearToken: jest.fn(async () => undefined) }));

const { clearToken } = jest.requireMock('@/lib/auth-storage');
const fetchMock = jest.fn();
(globalThis as { fetch: unknown }).fetch = fetchMock;

const respuesta = (status: number, cuerpo: unknown = {}) => ({
  ok: status >= 200 && status < 300,
  status,
  json: async () => cuerpo,
});

beforeEach(() => {
  consumirSesionVencida();
  (getToken as jest.Mock).mockResolvedValue('token-1');
});

describe('sesión expirada y renovación', () => {
  it('envía el token y devuelve el JSON', async () => {
    fetchMock.mockResolvedValue(respuesta(200, { ok: 1 }));

    await expect(apiRequest('/me')).resolves.toEqual({ ok: 1 });
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe('Bearer token-1');
  });

  it('ante un 401 con sesión: limpia el token, marca el vencimiento y vuelve al login', async () => {
    fetchMock.mockResolvedValue(respuesta(401, { message: 'Unauthenticated.' }));

    await expect(apiRequest('/panel')).rejects.toMatchObject({ status: 401 });
    await new Promise((r) => setImmediate(r));

    expect(clearToken).toHaveBeenCalledTimes(1);
    expect(router.replace).toHaveBeenCalledWith('/');
    expect(consumirSesionVencida()).toBe(true);
    expect(consumirSesionVencida()).toBe(false);
  });

  it('no trata como vencimiento el 401 del propio logout ni el de pedidos sin token', async () => {
    fetchMock.mockResolvedValue(respuesta(401));
    await expect(apiRequest('/logout', { method: 'POST' })).rejects.toBeInstanceOf(ApiError);

    (getToken as jest.Mock).mockResolvedValue(null);
    await expect(apiRequest('/app/login', { method: 'POST' })).rejects.toBeInstanceOf(ApiError);

    expect(clearToken).not.toHaveBeenCalled();
  });
});

describe('errores', () => {
  it('usa el mensaje de la API (422)', async () => {
    fetchMock.mockResolvedValue(respuesta(422, { message: 'Código incorrecto.' }));
    await expect(apiRequest('/x')).rejects.toMatchObject({ status: 422, message: 'Código incorrecto.' });
  });

  it('si no hay red lanza NetworkError', async () => {
    fetchMock.mockRejectedValue(new TypeError('Network request failed'));
    await expect(apiRequest('/x')).rejects.toBeInstanceOf(NetworkError);
  });
});
