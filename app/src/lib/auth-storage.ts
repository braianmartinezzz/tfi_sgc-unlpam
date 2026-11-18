import * as SecureStore from 'expo-secure-store';

/**
 * Acceso al token de sesión.
 *
 * `expo-secure-store` solo existe en iOS/Android: en web su módulo nativo es
 * un objeto vacío y llamarlo directamente revienta con
 * "X.getValueWithKeyAsync is not a function". Por eso:
 *
 * 1. Consultamos `isAvailableAsync()` antes de cada uso y envolvemos las
 *    llamadas: si algo falla, la sesión simplemente no se persiste y la app
 *    sigue funcionando en lugar de crashear.
 * 2. En web (o si el almacenamiento seguro no está disponible) usamos
 *    `localStorage` como respaldo para que la sesión sí persista.
 */

const AUTH_TOKEN_KEY = 'auth_token';

export async function isSecureStoreAvailable(): Promise<boolean> {
  try {
    return await SecureStore.isAvailableAsync();
  } catch (error) {
    console.warn('SecureStore no está disponible:', error);
    return false;
  }
}

/** Storage de respaldo solo cuando hay navegador (web). */
function getWebStorage(): Storage | null {
  try {
    if (typeof window === 'undefined') return null;
    // Probe: puede lanzar en modo privado o si las cookies están bloqueadas.
    window.localStorage.setItem('__auth_probe__', '1');
    window.localStorage.removeItem('__auth_probe__');
    return window.localStorage;
  } catch {
    return null;
  }
}

export async function getToken(): Promise<string | null> {
  if (await isSecureStoreAvailable()) {
    try {
      return await SecureStore.getItemAsync(AUTH_TOKEN_KEY);
    } catch (error) {
      console.warn('No se pudo leer el token de sesión:', error);
    }
  }
  return getWebStorage()?.getItem(AUTH_TOKEN_KEY) ?? null;
}

export async function setToken(token: string): Promise<void> {
  if (await isSecureStoreAvailable()) {
    try {
      await SecureStore.setItemAsync(AUTH_TOKEN_KEY, token);
      return;
    } catch (error) {
      console.warn('No se pudo guardar el token de sesión:', error);
    }
  }
  const storage = getWebStorage();
  if (storage) {
    storage.setItem(AUTH_TOKEN_KEY, token);
  } else {
    console.warn('Sin almacenamiento disponible: la sesión no se guardará.');
  }
}

export async function clearToken(): Promise<void> {
  if (await isSecureStoreAvailable()) {
    try {
      await SecureStore.deleteItemAsync(AUTH_TOKEN_KEY);
    } catch (error) {
      console.warn('No se pudo eliminar el token de sesión:', error);
    }
  }
  try {
    getWebStorage()?.removeItem(AUTH_TOKEN_KEY);
  } catch {
    // storage no disponible: nada que limpiar
  }
}
