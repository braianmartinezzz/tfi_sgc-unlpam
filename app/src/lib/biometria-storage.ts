import * as SecureStore from 'expo-secure-store';

import { isSecureStoreAvailable } from '@/lib/auth-storage';

/**
 * Token del dispositivo para el ingreso con biometría.
 *
 * Es distinto del token de sesión: la API lo entrega al activar la opción en
 * "Mi perfil" y solo sirve para abrir una sesión nueva en /app/login/biometrico.
 * Se guarda únicamente en el almacenamiento seguro del celular (no hay respaldo
 * en web) y sobrevive al cierre de sesión, para poder volver a entrar con la huella.
 */
const BIOMETRIA_TOKEN_KEY = 'biometria_token_dispositivo';

export async function getTokenDispositivo(): Promise<string | null> {
  if (!(await isSecureStoreAvailable())) return null;

  try {
    return await SecureStore.getItemAsync(BIOMETRIA_TOKEN_KEY);
  } catch (error) {
    console.warn('No se pudo leer el token de biometría:', error);
    return null;
  }
}

export async function setTokenDispositivo(token: string): Promise<void> {
  if (!(await isSecureStoreAvailable())) {
    throw new Error('Este dispositivo no tiene almacenamiento seguro.');
  }

  await SecureStore.setItemAsync(BIOMETRIA_TOKEN_KEY, token);
}

export async function clearTokenDispositivo(): Promise<void> {
  if (!(await isSecureStoreAvailable())) return;

  try {
    await SecureStore.deleteItemAsync(BIOMETRIA_TOKEN_KEY);
  } catch (error) {
    console.warn('No se pudo eliminar el token de biometría:', error);
  }
}
