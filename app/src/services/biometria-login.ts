import * as LocalAuthentication from 'expo-local-authentication';

import { isSecureStoreAvailable } from '@/lib/auth-storage';

/**
 * El celular tiene sensor biométrico, hay una huella o rostro cargado y se puede guardar el token.
 * (El registro de rostro y huella de "Mi identidad" está en lib/biometria.ts: es otro flujo.)
 */
export async function biometriaDisponible(): Promise<boolean> {
  try {
    if (!(await isSecureStoreAvailable())) return false;

    const [hardware, cargada] = await Promise.all([
      LocalAuthentication.hasHardwareAsync(),
      LocalAuthentication.isEnrolledAsync(),
    ]);

    return hardware && cargada;
  } catch {
    return false;
  }
}

/** Muestra el cuadro del sistema (huella o rostro). Devuelve true si el usuario se autenticó. */
export async function confirmarConBiometria(mensaje: string): Promise<boolean> {
  try {
    const resultado = await LocalAuthentication.authenticateAsync({
      promptMessage: mensaje,
      cancelLabel: 'Cancelar',
      // Solo huella o rostro: el PIN o patrón del celular no cuenta como biometría.
      disableDeviceFallback: true,
    });

    return resultado.success;
  } catch {
    return false;
  }
}
