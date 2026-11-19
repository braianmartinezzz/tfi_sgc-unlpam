import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';

import { useSgcTheme } from '@/hooks/use-sgc-theme';

export default function RootLayout() {
  // La preferencia guardada en la app (no el tema del sistema) decide el tema de navegación.
  const { scheme } = useSgcTheme();

  return (
    <ThemeProvider value={scheme === 'dark' ? DarkTheme : DefaultTheme}>
      {/* Pila de navegación: gesto/botón de volver nativo, y las pantallas anteriores
          (con sus filtros y scroll) quedan montadas. Cada pantalla dibuja su propio header. */}
      <Stack screenOptions={{ headerShown: false }} />
    </ThemeProvider>
  );
}
