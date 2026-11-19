import { DarkTheme, DefaultTheme, Stack, ThemeProvider } from 'expo-router';
import { useColorScheme } from 'react-native';

export default function RootLayout() {
  const colorScheme = useColorScheme();

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      {/* Pila de navegación: gesto/botón de volver nativo, y las pantallas anteriores
          (con sus filtros y scroll) quedan montadas. Cada pantalla dibuja su propio header. */}
      <Stack screenOptions={{ headerShown: false }} />
    </ThemeProvider>
  );
}
