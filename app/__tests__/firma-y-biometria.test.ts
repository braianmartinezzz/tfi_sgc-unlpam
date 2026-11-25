import * as Location from 'expo-location';
import * as LocalAuthentication from 'expo-local-authentication';

import { biometriaDisponible, confirmarConBiometria } from '@/services/biometria-login';
import { UbicacionError, obtenerUbicacionParaFirma } from '@/services/ubicacion-firma';

jest.mock('expo-location', () => ({
  requestForegroundPermissionsAsync: jest.fn(),
  hasServicesEnabledAsync: jest.fn(),
  getCurrentPositionAsync: jest.fn(),
  Accuracy: { Balanced: 3 },
}));
jest.mock('expo-local-authentication', () => ({
  hasHardwareAsync: jest.fn(),
  isEnrolledAsync: jest.fn(),
  authenticateAsync: jest.fn(),
}));

const L = Location as jest.Mocked<typeof Location>;
const A = LocalAuthentication as jest.Mocked<typeof LocalAuthentication>;

describe('confirmación de permisos de ubicación', () => {
  beforeEach(() => {
    L.requestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted', canAskAgain: true } as never);
    L.hasServicesEnabledAsync.mockResolvedValue(true);
    L.getCurrentPositionAsync.mockResolvedValue({ coords: { latitude: -36.6, longitude: -64.3 } } as never);
  });

  it('con permiso devuelve latitud y longitud', async () => {
    await expect(obtenerUbicacionParaFirma()).resolves.toEqual({ latitud: -36.6, longitud: -64.3 });
  });

  it('permiso denegado: se puede volver a pedir', async () => {
    L.requestForegroundPermissionsAsync.mockResolvedValue({ status: 'denied', canAskAgain: true } as never);
    const error = await obtenerUbicacionParaFirma().catch((e) => e);

    expect(error).toBeInstanceOf(UbicacionError);
    expect(error.bloqueado).toBe(false);
  });

  it('permiso bloqueado: indica ir a los ajustes', async () => {
    L.requestForegroundPermissionsAsync.mockResolvedValue({ status: 'denied', canAskAgain: false } as never);
    expect(await obtenerUbicacionParaFirma().catch((e) => e)).toMatchObject({ bloqueado: true });
  });

  it('GPS apagado y falla de lectura tienen mensaje propio', async () => {
    L.hasServicesEnabledAsync.mockResolvedValue(false);
    await expect(obtenerUbicacionParaFirma()).rejects.toThrow(/GPS/);

    L.hasServicesEnabledAsync.mockResolvedValue(true);
    L.getCurrentPositionAsync.mockRejectedValue(new Error('timeout'));
    await expect(obtenerUbicacionParaFirma()).rejects.toThrow(/No se pudo obtener tu ubicación/);
  });
});

describe('biometría', () => {
  it('disponible solo con sensor y huella/rostro cargado', async () => {
    A.hasHardwareAsync.mockResolvedValue(true);
    A.isEnrolledAsync.mockResolvedValue(true);
    expect(await biometriaDisponible()).toBe(true);

    A.isEnrolledAsync.mockResolvedValue(false);
    expect(await biometriaDisponible()).toBe(false);
  });

  it('la confirmación nunca acepta el PIN del celular', async () => {
    A.authenticateAsync.mockResolvedValue({ success: true } as never);
    expect(await confirmarConBiometria('Firmar')).toBe(true);
    expect(A.authenticateAsync).toHaveBeenCalledWith(expect.objectContaining({ disableDeviceFallback: true }));

    A.authenticateAsync.mockResolvedValue({ success: false } as never);
    expect(await confirmarConBiometria('Firmar')).toBe(false);

    A.authenticateAsync.mockRejectedValue(new Error('lockout'));
    expect(await confirmarConBiometria('Firmar')).toBe(false);
  });
});
