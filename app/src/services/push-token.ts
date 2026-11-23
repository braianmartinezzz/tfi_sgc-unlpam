import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

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
