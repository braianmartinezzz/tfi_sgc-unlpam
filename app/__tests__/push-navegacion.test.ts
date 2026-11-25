import { router } from 'expo-router';

import { consumirDestinoPendiente } from '@/lib/destino-pendiente';
import { destinoDePush } from '@/lib/push-destino';
import { ApiError, NetworkError } from '@/services/api-client';
import { abrirDesdePush } from '@/services/push-apertura';

jest.mock('@/lib/auth-storage', () => ({ getToken: jest.fn() }));
jest.mock('@/services/api-client', () => {
  const actual = jest.requireActual('@/services/api-client');
  return { ...actual, apiRequest: jest.fn() };
});

const { getToken } = jest.requireMock('@/lib/auth-storage');
const { apiRequest } = jest.requireMock('@/services/api-client');
const FICHA = { pathname: '/convenio/[id]', params: { id: '12' } };

beforeEach(() => {
  consumirDestinoPendiente();
  getToken.mockResolvedValue('t');
  apiRequest.mockResolvedValue({});
});

describe('destino según el aviso', () => {
  it('convenio, lista y código MFA', () => {
    expect(destinoDePush({ id_instrumento: 12 })).toEqual(FICHA);
    expect(destinoDePush({ id_instrumento: '12' })).toEqual(FICHA);
    expect(destinoDePush({ id_instrumento: null })).toBe('/notificaciones');
    expect(destinoDePush({ id_instrumento: -3 })).toBe('/notificaciones');
    expect(destinoDePush({})).toBe('/notificaciones');
    expect(destinoDePush({ tipo: 'mfa' })).toBeNull();
  });
});

describe('apertura de un aviso', () => {
  it('con sesión válida abre el destino', async () => {
    await abrirDesdePush({ id_instrumento: 12 });
    expect(router.push).toHaveBeenCalledWith(FICHA);
  });

  it('la push del código MFA no navega', async () => {
    await abrirDesdePush({ tipo: 'mfa' });
    expect(router.push).not.toHaveBeenCalled();
    expect(router.replace).not.toHaveBeenCalled();
  });

  it('sin token va al login y recuerda el destino', async () => {
    getToken.mockResolvedValue(null);
    await abrirDesdePush({ id_instrumento: 12 });

    expect(router.replace).toHaveBeenCalledWith('/');
    expect(router.push).not.toHaveBeenCalled();
    expect(consumirDestinoPendiente()).toEqual(FICHA);
    expect(consumirDestinoPendiente()).toBeNull();
  });

  it('con sesión vencida (401) no navega y recuerda el destino', async () => {
    apiRequest.mockRejectedValue(new ApiError(401, 'Unauthenticated.'));
    await abrirDesdePush({ id_instrumento: 12 });

    expect(router.push).not.toHaveBeenCalled();
    expect(consumirDestinoPendiente()).toEqual(FICHA);
  });

  it('sin conexión abre igual (datos guardados)', async () => {
    apiRequest.mockRejectedValue(new NetworkError());
    await abrirDesdePush({ id_instrumento: 12 });
    expect(router.push).toHaveBeenCalledWith(FICHA);
  });

  it('el destino pendiente caduca', () => {
    const { guardarDestinoPendiente } = jest.requireActual('@/lib/destino-pendiente');
    guardarDestinoPendiente('/notificaciones', 0);
    expect(consumirDestinoPendiente(6 * 60 * 1000)).toBeNull();
  });
});
