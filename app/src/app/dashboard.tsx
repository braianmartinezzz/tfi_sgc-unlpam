import { useRouter } from 'expo-router';
import { useEffect } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { clearToken, getToken } from '@/lib/auth-storage';

export default function DashboardScreen() {
  const theme = useTheme();
  const router = useRouter();

  useEffect(() => {
    const verifySession = async () => {
      const token = await getToken();
      if (!token) {
        router.replace('/');
      }
    };

    verifySession();
  }, [router]);

  const handleLogout = async () => {
    await clearToken();
    router.replace('/');
  };

  const handleBack = async () => {
    await clearToken();
    router.replace('/');
  };

  return (
    <ThemedView style={styles.container}>
      <SafeAreaView style={styles.safeArea}>
        <View style={styles.card}>
          <ThemedText style={styles.title}>UNLPam</ThemedText>
          <ThemedText themeColor="textSecondary" style={styles.subtitle}>
            Sistema de Gestión de Convenios
          </ThemedText>

          <ThemedText style={styles.greeting}>¡Bienvenido!</ThemedText>
          <ThemedText themeColor="textSecondary" style={styles.description}>
            La sesión quedó iniciada correctamente y esta es la pantalla limpia de inicio.
          </ThemedText>

          <Pressable
            onPress={handleBack}
            style={({ pressed }) => [
              styles.button,
              { backgroundColor: theme.backgroundElement },
              pressed && { opacity: 0.8 },
            ]}>
            <ThemedText style={styles.buttonText}>Volver</ThemedText>
          </Pressable>

          <Pressable
            onPress={handleLogout}
            style={({ pressed }) => [
              styles.secondaryButton,
              { backgroundColor: 'transparent', borderColor: theme.textSecondary },
              pressed && { opacity: 0.8 },
            ]}>
            <ThemedText style={styles.secondaryButtonText}>Cerrar sesión</ThemedText>
          </Pressable>
        </View>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  safeArea: {
    width: '100%',
    maxWidth: MaxContentWidth,
    paddingHorizontal: Spacing.four,
  },
  card: {
    gap: Spacing.three,
    padding: Spacing.five,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(128, 128, 128, 0.2)',
    backgroundColor: 'rgba(255,255,255,0.04)',
  },
  title: {
    fontSize: 36,
    lineHeight: 42,
    fontWeight: '800',
    letterSpacing: -1,
  },
  subtitle: {
    fontSize: 16,
    lineHeight: 24,
  },
  greeting: {
    fontSize: 28,
    lineHeight: 34,
    fontWeight: '700',
  },
  description: {
    fontSize: 16,
    lineHeight: 24,
  },
  button: {
    marginTop: Spacing.two,
    paddingVertical: Spacing.three,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondaryButton: {
    paddingVertical: Spacing.three,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    fontWeight: '700',
  },
  secondaryButtonText: {
    fontWeight: '600',
    opacity: 0.9,
  },
});
