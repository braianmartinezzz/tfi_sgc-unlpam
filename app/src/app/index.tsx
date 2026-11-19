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

import { Txt } from '@/components/panel/txt';
import { API_URL } from '@/constants/api';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { getToken, setToken } from '@/lib/auth-storage';

export default function LoginScreen() {
  const tema = useSgcTheme();
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
        body: JSON.stringify({ mail: mail.trim(), password }),
      });
      const data = await res.json();

      if (!res.ok) {
        const firstFieldError = data?.errors
          ? Object.values<string[]>(data.errors)[0]?.[0]
          : null;
        setError(firstFieldError ?? data?.message ?? 'No se pudo iniciar sesión.');
        return;
      }

      await setToken(data.access_token);
      router.replace('/dashboard');
    } catch (loginError) {
      if (loginError instanceof Error && loginError.name === 'AbortError') {
        setError(`No se pudo conectar con el servidor (${API_URL}). Verificá que Laravel esté levantado.`);
        return;
      }

      setError(`No se pudo conectar con el servidor (${API_URL}).`);
    } finally {
      clearTimeout(timeoutId);
      setLoading(false);
    }
  }

  // Paleta de la app (misma que el resto de las pantallas), según la preferencia claro/oscuro.
  const { surface, border, page, brandText, brand } = tema;

  const inputStyle = [
    styles.input,
    { backgroundColor: surface, borderColor: border, color: tema.text },
  ];

  if (checkingSession) {
    return (
      <View style={[styles.container, { backgroundColor: page }]}>
        <SafeAreaView style={styles.safeAreaCentered}>
          <ActivityIndicator size="large" color={brand} />
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: page }]}>
      <SafeAreaView style={styles.safeArea}>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.centered}>
          <View style={[styles.card, { backgroundColor: surface, borderColor: border }]}>
            <View style={[styles.brandLine, { backgroundColor: brand }]} />

            <View style={styles.cardContent}>
              <View style={styles.header}>
                <Txt style={[styles.brandText, { color: brandText }]}>UNLPam</Txt>
                <Txt variante="detalle" color="textSecondary" style={styles.brandSubtext}>
                  Sistema de Gestión de Convenios
                </Txt>

                <Txt style={[styles.systemTitle, { color: tema.text }]}>
                  Sistema de Gestión de Convenios
                </Txt>
                <Txt style={[styles.loginTitle, { color: tema.text }]}>
                  Iniciar Sesión
                </Txt>
              </View>

              <View style={styles.formFields}>
                <View style={styles.field}>
                  <Txt variante="detalle" negrita style={styles.label}>Correo Electrónico</Txt>
                  <TextInput
                    style={inputStyle}
                    value={mail}
                    onChangeText={setMail}
                    placeholder="nombre@unlpam.edu.ar"
                    placeholderTextColor={tema.textSecondary}
                    autoCapitalize="none"
                    autoCorrect={false}
                    keyboardType="email-address"
                    textContentType="emailAddress"
                    autoComplete="email"
                    editable={!loading}
                  />
                </View>

                <View style={styles.field}>
                  <Txt variante="detalle" negrita style={styles.label}>Contraseña</Txt>
                  <View>
                    <TextInput
                      style={[inputStyle, styles.passwordInput]}
                      value={password}
                      onChangeText={setPassword}
                      placeholder="••••••••"
                      placeholderTextColor={tema.textSecondary}
                      secureTextEntry={!showPassword}
                      autoCapitalize="none"
                      autoCorrect={false}
                      textContentType="password"
                      autoComplete="password"
                      onSubmitEditing={handleLogin}
                      returnKeyType="go"
                      editable={!loading}
                    />
                    <Pressable
                      accessibilityRole="button"
                      accessibilityLabel={showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                      style={styles.toggle}
                      onPress={() => setShowPassword((visible) => !visible)}>
                      <Txt variante="detalle" color="textSecondary">
                        {showPassword ? 'Ocultar' : 'Mostrar'}
                      </Txt>
                    </Pressable>
                  </View>
                </View>

                {error && (
                  <Txt variante="detalle" style={styles.error}>
                    {error}
                  </Txt>
                )}

                <Pressable
                  accessibilityRole="button"
                  onPress={handleLogin}
                  disabled={!canSubmit}
                  style={({ pressed }) => [
                    styles.button,
                    { backgroundColor: brand },
                    (!canSubmit || pressed) && styles.buttonDim,
                  ]}>
                  {loading ? (
                    <ActivityIndicator color="#ffffff" />
                  ) : (
                    <Txt style={styles.buttonText}>Iniciar Sesión</Txt>
                  )}
                </Pressable>

                <Pressable
                  accessibilityRole="button"
                  style={styles.forgotButton}
                  onPress={() => setError('La recuperación de contraseña estará disponible próximamente.') }>
                  <Txt style={[styles.forgotText, { color: brandText }]}>
                    Olvidé mi contraseña
                  </Txt>
                </Pressable>
              </View>
            </View>
          </View>

          <View style={styles.footer}>
            <Txt variante="detalle" color="textSecondary" style={styles.footerText}>
              Acceso exclusivo para personal autorizado.
            </Txt>
            <Txt variante="detalle" color="textSecondary" style={styles.footerText}>
              UNLPam - Facultad de Ingeniería, 2026.
            </Txt>
          </View>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center' },
  safeAreaCentered: {
    flex: 1,
    width: '100%',
    justifyContent: 'center',
    alignItems: 'center',
  },
  safeArea: {
    flex: 1,
    width: '100%',
    maxWidth: 420,
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingVertical: 24,
  },
  card: {
    width: '100%',
    borderWidth: 1,
    borderRadius: 6,
    overflow: 'hidden',
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.18,
    shadowRadius: 16,
    elevation: 8,
  },
  brandLine: {
    height: 5,
    width: '100%',
  },
  cardContent: {
    paddingHorizontal: 24,
    paddingTop: 28,
    paddingBottom: 32,
  },
  header: {
    alignItems: 'center',
  },
  brandText: {
    fontSize: 42,
    lineHeight: 46,
    fontWeight: '800',
    letterSpacing: -1,
    textAlign: 'center',
  },
  brandSubtext: {
    marginTop: 4,
    textAlign: 'center',
  },
  systemTitle: {
    marginTop: 24,
    fontSize: 20,
    lineHeight: 26,
    fontWeight: '500',
    textAlign: 'center',
  },
  loginTitle: {
    marginTop: 4,
    fontSize: 20,
    lineHeight: 26,
    fontWeight: '400',
    textAlign: 'center',
  },
  formFields: {
    marginTop: 24,
    gap: 12,
  },
  field: { gap: 4 },
  label: { lineHeight: 20 },
  input: {
    height: 48,
    borderRadius: 6,
    borderWidth: 1,
    paddingHorizontal: 14,
    fontSize: 16,
  },
  passwordInput: { paddingRight: 86 },
  toggle: {
    position: 'absolute',
    right: 12,
    top: 0,
    bottom: 0,
    justifyContent: 'center',
  },
  error: {
    color: '#D93025',
    textAlign: 'center',
  },
  button: {
    height: 48,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 8,
  },
  buttonDim: { opacity: 0.65 },
  buttonText: {
    color: '#ffffff',
    fontSize: 16,
    lineHeight: 22,
    fontWeight: '600',
  },
  forgotButton: {
    alignItems: 'center',
    minHeight: 32,
    justifyContent: 'center',
  },
  forgotText: {
    fontSize: 14,
  },
  footer: {
    marginTop: 24,
    alignItems: 'center',
  },
  footerText: {
    textAlign: 'center',
  },
});
