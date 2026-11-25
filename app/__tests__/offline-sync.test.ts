import NetInfo from '@react-native-community/netinfo';

import {
  agregarCola,
  asegurarCacheDelUsuario,
  guardarOffline,
  leerCola,
  leerOffline,
  limpiarOffline,
} from '@/lib/offline-storage';
import { leerInstantaneaEnMemoria, guardarInstantaneaEnMemoria } from '@/lib/panel-memoria';
import { ApiError, NetworkError } from '@/services/api-client';
import { MAX_INTENTOS, sincronizarCola } from '@/services/offline-sync';
import { marcarNotificacionLeida } from '@/services/notificaciones';

jest.mock('@/services/api-client', () => {
  const actual = jest.requireActual('@/services/api-client');
  return { ...actual, apiRequest: jest.fn() };
});

const { apiRequest } = jest.requireMock('@/services/api-client');
const conRed = (valor: boolean) =>
  (NetInfo.fetch as jest.Mock).mockResolvedValue({ isConnected: valor, isInternetReachable: valor });

beforeEach(async () => {
  await limpiarOffline();
  conRed(true);
  apiRequest.mockReset();
});

const encolar = (path: string) => agregarCola({ method: 'PATCH', path });

describe('cola offline y sincronización', () => {
  it('sin conexión no envía nada y conserva la cola', async () => {
    await encolar('/a');
    conRed(false);

    expect(await sincronizarCola()).toEqual({ procesadas: 0, descartadas: 0, pendientes: 1 });
    expect(apiRequest).not.toHaveBeenCalled();
  });

  it('con conexión envía en orden y vacía la cola', async () => {
    await encolar('/a');
    await encolar('/b');
    apiRequest.mockResolvedValue({});

    expect(await sincronizarCola()).toEqual({ procesadas: 2, descartadas: 0, pendientes: 0 });
    expect(apiRequest.mock.calls.map((c: unknown[]) => c[0])).toEqual(['/a', '/b']);
  });

  it('si la red cae a mitad de camino, lo ya enviado no se reenvía después', async () => {
    await encolar('/a');
    await encolar('/b');
    apiRequest.mockResolvedValueOnce({}).mockRejectedValueOnce(new NetworkError());

    expect(await sincronizarCola()).toMatchObject({ procesadas: 1, pendientes: 1 });
    expect((await leerCola()).map((o) => o.path)).toEqual(['/b']);

    apiRequest.mockResolvedValue({});
    await sincronizarCola();
    expect(apiRequest.mock.calls.map((c: unknown[]) => c[0])).toEqual(['/a', '/b', '/b']);
  });

  it('un rechazo definitivo (422/404) se descarta y no traba las demás', async () => {
    await encolar('/mala');
    await encolar('/buena');
    apiRequest.mockRejectedValueOnce(new ApiError(404, 'No existe')).mockResolvedValueOnce({});

    expect(await sincronizarCola()).toEqual({ procesadas: 1, descartadas: 1, pendientes: 0 });
  });

  it('con 401 corta y conserva todo para cuando se vuelva a iniciar sesión', async () => {
    await encolar('/a');
    apiRequest.mockRejectedValue(new ApiError(401, 'Unauthenticated.'));

    expect(await sincronizarCola()).toMatchObject({ procesadas: 0, descartadas: 0, pendientes: 1 });
    expect((await leerCola())[0].attempts).toBe(0);
  });

  it('una falla del servidor cuenta intentos y al llegar al máximo se descarta', async () => {
    await encolar('/a');
    apiRequest.mockRejectedValue(new ApiError(500, 'Error'));

    for (let i = 1; i < MAX_INTENTOS; i++) {
      await sincronizarCola();
      expect((await leerCola())[0].attempts).toBe(i);
    }

    expect(await sincronizarCola()).toMatchObject({ descartadas: 1, pendientes: 0 });
  });

  it('marcar una notificación como leída sin red queda en la cola', async () => {
    apiRequest.mockRejectedValueOnce(new NetworkError());

    expect(await marcarNotificacionLeida(7)).toBe(false);
    expect(await leerCola()).toMatchObject([{ method: 'PATCH', path: '/notificaciones/7/leida' }]);
  });

  it('un error de la API al marcar leída no se encola', async () => {
    apiRequest.mockRejectedValueOnce(new ApiError(404, 'No existe'));

    await expect(marcarNotificacionLeida(7)).rejects.toBeInstanceOf(ApiError);
    expect(await leerCola()).toEqual([]);
  });
});

describe('lectura offline del panel: datos por usuario', () => {
  const instantanea = { me: { id_usuario: 1 }, panel: {}, actualizado: new Date() } as never;

  it('el mismo usuario conserva panel y cola; otro usuario los pierde', async () => {
    await asegurarCacheDelUsuario(1);
    await guardarOffline('panel_latest', { dato: 1 });
    guardarInstantaneaEnMemoria(instantanea);
    await encolar('/a');

    await asegurarCacheDelUsuario(1);
    expect(await leerOffline('panel_latest')).toEqual({ dato: 1 });
    expect(await leerCola()).toHaveLength(1);

    await asegurarCacheDelUsuario(2);
    expect(await leerOffline('panel_latest')).toBeNull();
    expect(await leerCola()).toEqual([]);
    expect(leerInstantaneaEnMemoria()).toBeNull();
    expect(await leerOffline('duenio_cache')).toBe(2);
  });
});
