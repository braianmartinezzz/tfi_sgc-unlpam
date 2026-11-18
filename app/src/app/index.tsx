import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { API_URL } from '@/constants/api';
import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';
import { getToken, setToken } from '@/lib/auth-storage';

export default function LoginScreen() {
  const theme = useTheme();
  const router = useRouter();

  const [mail, setMail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [checkingSession, setCheckingSession] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const checkSession = async () => {
      try {
        const token = await getToken();
        if (token) {
          router.replace('/dashboard');
        }
      } finally {
        setCheckingSession(false);
      }
    };

    checkSession();
  }, [router]);

  const canSubmit = mail.trim().length > 0 && password.length > 0 && !loading;

  async function handleLogin() {
    if (!canSubmit) return;
    setLoading(true);
    setError(null);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 15000);

    try {
      const res = await fetch(`${API_URL}/api/login`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        signal: controller.signal,
        // Ojo: el backend espera "mail", no "email"
        body: JSON.stringify({ mail: mail.trim(), password }),
      });
      const data = await res.json();

      if (!res.ok) {
        // 401: credenciales inválidas | 422: datos inválidos (data.errors)
        const firstFieldError = data?.errors ? Object.values<string[]>(data.errors)[0]?.[0] : null;
        setError(firstFieldError ?? data?.message ?? 'No se pudo iniciar sesión.');
        return;
      }

      // data.access_token -> token Bearer para el resto de los endpoints
      // data.user         -> datos del usuario (rol, unidad académica, etc.)
      await setToken(data.access_token);
      console.log('Login OK', data.user);
      router.replace('/dashboard');
    } catch (error) {
      if (error instanceof Error && error.name === 'AbortError') {
        setError(`No se pudo conectar con el servidor (${API_URL}). Verificá que Laravel esté levantado.`);
        return;
      }

      setError(`No se pudo conectar con el servidor (${API_URL}).`);
    } finally {
      clearTimeout(timeoutId);
      setLoading(false);
    }
  }

  const inputStyle = [
    styles.input,
    { backgroundColor: theme.backgroundElement, color: theme.text },
  ];

  if (checkingSession) {
    return (
      <ThemedView style={styles.container}>
        <SafeAreaView style={styles.safeArea}>
          <ActivityIndicator size="large" color="#208AEF" />
        </SafeAreaView>
      </ThemedView>
    );
  }

  return (
    <ThemedView style={styles.container}>
      <SafeAreaView style={styles.safeArea}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.form}>
          <View style={styles.header}>
            <View style={styles.brandContainer}>
              <View style={styles.brandAccent} />
              <ThemedText type="title" style={styles.brandText}>
                UNLPam
              </ThemedText>
              <ThemedText type="smallBold" themeColor="textSecondary" style={styles.brandSubtext}>
                Sistema de Gestión de Convenios
              </ThemedText>
            </View>
            <ThemedText type="subtitle">Iniciar sesión</ThemedText>
          </View>

          <View style={styles.field}>
            <ThemedText type="smallBold">Correo</ThemedText>
            <TextInput
              style={inputStyle}
              value={mail}
              onChangeText={setMail}
              placeholder="usuario@unlpam.edu.ar"
              placeholderTextColor={theme.textSecondary}
              autoCapitalize="none"
              autoCorrect={false}
              keyboardType="email-address"
              textContentType="emailAddress"
              autoComplete="email"
              editable={!loading}
            />
          </View>

          <View style={styles.field}>
            <ThemedText type="smallBold">Contraseña</ThemedText>
            <View>
              <TextInput
                style={[inputStyle, styles.passwordInput]}
                value={password}
                onChangeText={setPassword}
                placeholder="••••••••"
                placeholderTextColor={theme.textSecondary}
                secureTextEntry={!showPassword}
                autoCapitalize="none"
                autoCorrect={false}
                textContentType="password"
                autoComplete="password"
                onSubmitEditing={handleLogin}
                returnKeyType="go"
                editable={!loading}
              />
              <Pressable style={styles.toggle} onPress={() => setShowPassword((v) => !v)}>
                <ThemedText type="small" themeColor="textSecondary">
                  {showPassword ? 'Ocultar' : 'Mostrar'}
                </ThemedText>
              </Pressable>
            </View>
          </View>

          {error && (
            <ThemedText type="small" style={styles.error}>
              {error}
            </ThemedText>
          )}

          <Pressable
            onPress={handleLogin}
            disabled={!canSubmit}
            style={({ pressed }) => [
              styles.button,
              (!canSubmit || pressed) && styles.buttonDim,
            ]}>
            {loading ? (
              <ActivityIndicator color="#ffffff" />
            ) : (
              <ThemedText style={styles.buttonText}>Ingresar</ThemedText>
            )}
          </Pressable>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center' },
  safeArea: {
    flex: 1,
    width: '100%',
    maxWidth: MaxContentWidth,
    justifyContent: 'center',
    paddingHorizontal: Spacing.four,
  },
  form: { gap: Spacing.three },
  header: { gap: Spacing.one, marginBottom: Spacing.three, alignItems: 'center' },
  brandContainer: { alignItems: 'center', gap: Spacing.one },
  brandAccent: {
    width: 56,
    height: 5,
    borderRadius: 999,
    backgroundColor: '#208AEF',
  },
  brandText: {
    fontSize: 42,
    lineHeight: 48,
    fontWeight: '800',
    letterSpacing: -1,
    textAlign: 'center',
  },
  brandSubtext: {
    textAlign: 'center',
  },
  field: { gap: Spacing.two },
  input: {
    height: 48,
    borderRadius: 12,
    paddingHorizontal: Spacing.three,
    fontSize: 16,
  },
  passwordInput: { paddingRight: 90 },
  toggle: {
    position: 'absolute',
    right: Spacing.three,
    top: 0,
    bottom: 0,
    justifyContent: 'center',
  },
  error: { color: '#D93025' },
  button: {
    height: 48,
    borderRadius: 12,
    backgroundColor: '#208AEF',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: Spacing.two,
  },
  buttonDim: { opacity: 0.6 },
  buttonText: { color: '#ffffff', fontWeight: '600' },
});
