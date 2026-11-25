import { agregarCola, guardarOffline, leerCola, leerOffline } from '@/lib/offline-storage';
import { ApiError, NetworkError } from '@/services/api-client';
import {
  activarBiometria,
  enviarCodigoMfa,
  iniciarLogin,
  loginBiometrico,
  mensajeDeError,
  verificarCodigoMfa,
} from '@/services/auth-app';
import { cerrarSesion } from '@/services/auth';

jest.mock('@/lib/auth-storage', () => ({ getToken: jest.fn(), clearToken: jest.fn(async () => undefined) }));
jest.mock('@/services/push-token', () => ({ eliminarPushToken: jest.fn(async () => undefined) }));
jest.mock('@/services/api-client', () => {
  const actual = jest.requireActual('@/services/api-client');
  return { ...actual, apiRequest: jest.fn() };
});

const { apiRequest } = jest.requireMock('@/services/api-client');
const { clearToken } = jest.requireMock('@/lib/auth-storage');

describe('login con correo, SMS y push', () => {
  beforeEach(() => apiRequest.mockResolvedValue({}));

  it('paso 1 envía mail y contraseña', async () => {
    await iniciarLogin('a@unlpam.edu.ar', 'clave');
    expect(apiRequest).toHaveBeenCalledWith('/app/login', {
      method: 'POST',
      body: JSON.stringify({ mail: 'a@unlpam.edu.ar', password: 'clave' }),
    });
  });

  it.each([
    ['mail', undefined, null],
    ['sms', undefined, null],
    ['push', 'ExponentPushToken[abc]', 'ExponentPushToken[abc]'],
  ] as const)('paso 2 por %s', async (canal, entrada, esperado) => {
    await enviarCodigoMfa('mfa-1', canal, entrada);
    expect(JSON.parse(apiRequest.mock.calls[0][1].body)).toEqual({
      mfa_token: 'mfa-1',
      canal,
      push_token: esperado,
    });
  });

  it('paso 3 verifica el código', async () => {
    await verificarCodigoMfa('mfa-1', '123456');
    expect(JSON.parse(apiRequest.mock.calls[0][1].body)).toEqual({ mfa_token: 'mfa-1', codigo: '123456' });
  });

  it('muestra el mensaje de la API o de red, y uno por defecto para lo inesperado', () => {
    expect(mensajeDeError(new ApiError(422, 'Código incorrecto.'), 'x')).toBe('Código incorrecto.');
    expect(mensajeDeError(new NetworkError(), 'x')).toMatch(/conectar/);
    expect(mensajeDeError(new Error('raro'), 'Falló')).toBe('Falló');
  });
});

describe('activación y uso de biometría', () => {
  it('activar devuelve el token del dispositivo', async () => {
    apiRequest.mockResolvedValue({ token_dispositivo: 'dev-1' });
    await expect(activarBiometria('clave')).resolves.toBe('dev-1');
  });

  it('el ingreso usa el token del dispositivo como Bearer', async () => {
    apiRequest.mockResolvedValue({});
    await loginBiometrico('dev-1');
    expect(apiRequest).toHaveBeenCalledWith('/app/login/biometrico', {
      method: 'POST',
      headers: { Authorization: 'Bearer dev-1' },
    });
  });
});

describe('cerrar sesión', () => {
  it('cierra aunque el servidor no responda y borra los datos offline', async () => {
    apiRequest.mockRejectedValue(new NetworkError());
    await guardarOffline('panel_latest', { dato: 1 });
    await agregarCola({ method: 'PATCH', path: '/a' });

    await expect(cerrarSesion()).resolves.toBeUndefined();

    expect(clearToken).toHaveBeenCalled();
    expect(await leerOffline('panel_latest')).toBeNull();
    expect(await leerCola()).toEqual([]);
  });
});
