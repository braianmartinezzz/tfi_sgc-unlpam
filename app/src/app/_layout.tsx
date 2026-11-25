import { useEffect } from 'react';
import { Platform } from 'react-native';
import { router, DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';

import { useSgcTheme } from '@/hooks/use-sgc-theme';

export default function RootLayout() {
  // La preferencia guardada en la app (no el tema del sistema) decide el tema de navegación.
  const { scheme } = useSgcTheme();

  function abrirDesdePush(data: Record<string, unknown>): void {
    const id = Number(data.id_instrumento);

    if (Number.isInteger(id) && id > 0) {
      router.push({ pathname: '/convenio/[id]', params: { id: String(id) } });
    } else {
      router.push('/notificaciones');
    }
  }

  useEffect(() => {
    if (Platform.OS === 'web') return;

    let suscripcion: { remove: () => void } | null = null;

    void (async () => {
      const Notifications = await import('expo-notifications');

      Notifications.setNotificationHandler({
        handleNotification: async () => ({
          shouldShowBanner: true,
          shouldShowList: true,
          shouldPlaySound: true,
          shouldSetBadge: true,
        }),
      });

      const respuestaInicial = await Notifications.getLastNotificationResponseAsync();
      if (respuestaInicial) {
        abrirDesdePush(respuestaInicial.notification.request.content.data ?? {});
        await Notifications.clearLastNotificationResponseAsync();
      }

      suscripcion = Notifications.addNotificationResponseReceivedListener((respuesta) => {
        abrirDesdePush(respuesta.notification.request.content.data ?? {});
      });
    })();

    return () => {
      suscripcion?.remove();
    };
  }, []);

  return (
    <ThemeProvider value={scheme === 'dark' ? DarkTheme : DefaultTheme}>
      {/* Pila de navegación: gesto/botón de volver nativo, y las pantallas anteriores
          (con sus filtros y scroll) quedan montadas. Cada pantalla dibuja su propio header. */}
      <Stack screenOptions={{ headerShown: false }} />
    </ThemeProvider>
  );
}
