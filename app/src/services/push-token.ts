import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { apiRequest } from '@/services/api-client';

/**
 * Obtiene el token de notificaciones push de Expo de este dispositivo.
 *
 * Se usa para que la API pueda mandar el código de verificación (MFA) como
 * notificación push. Devuelve null si no se puede: web, emulador, permiso
 * denegado, proyecto sin `projectId` de EAS o Expo Go en Android (desde el SDK 53
 * las push remotas necesitan un development build). Quien llama debe ofrecer
 * otro canal en ese caso.
 */
export async function obtenerExpoPushToken(): Promise<string | null> {
  if (Platform.OS === 'web' || !Device.isDevice) return null;

  try {
    const Notifications = await import('expo-notifications');

    // Sin este manejador, una notificación que llega con la app abierta no se muestra.
    Notifications.setNotificationHandler({
      handleNotification: async () => ({
        shouldShowBanner: true,
        shouldShowList: true,
        shouldPlaySound: false,
        shouldSetBadge: false,
      }),
    });

    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'Notificaciones',
        importance: Notifications.AndroidImportance.MAX,
      });
    }

    let { status } = await Notifications.getPermissionsAsync();

    if (status !== 'granted') {
      ({ status } = await Notifications.requestPermissionsAsync());
    }

    if (status !== 'granted') return null;

    const projectId =
      Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;

    if (!projectId) return null;

    const token = await Notifications.getExpoPushTokenAsync({ projectId });

    return token.data;
  } catch (error) {
    console.warn('No se pudo obtener el token de notificaciones push:', error);
    return null;
  }
}

let ultimoTokenRegistrado: string | null = null;

/**
 * Registra el token de notificaciones push en la API.
 *
 * Se debe llamar después de obtener el token con `obtenerExpoPushToken` y
 * después de que el usuario inicie sesión. El token se asocia a la cuenta del
 * usuario para poder enviarle notificaciones push.
 */
export async function registrarPushToken(): Promise<string | null> {
  const token = await obtenerExpoPushToken();

  if (!token) return null;

  await apiRequest('/notificaciones/push', {
    method: 'POST',
    body: JSON.stringify({
      push_token: token,
      dispositivo: `${Platform.OS}-${Device.modelName ?? 'desconocido'}`,
    }),
  });

  ultimoTokenRegistrado = token;
  return token;
}

/**
 * Elimina el token de notificaciones push de la API.
 *
 * Se debe llamar cuando el usuario cierra sesión, para que deje de recibir
 * notificaciones push asociadas a su cuenta.
 */
export async function eliminarPushToken(token?: string | null): Promise<void> {
  const aEliminar = token ?? ultimoTokenRegistrado;
  if (!aEliminar) return;

  await apiRequest('/notificaciones/push', {
    method: 'DELETE',
    body: JSON.stringify({ push_token: aEliminar }),
  });

  ultimoTokenRegistrado = null;
}
