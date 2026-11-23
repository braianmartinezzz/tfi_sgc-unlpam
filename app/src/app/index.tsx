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

import { VerificacionMfa } from '@/components/auth/verificacion-mfa';
import { Txt } from '@/components/panel/txt';
import { API_URL } from '@/constants/api';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { getToken, setToken } from '@/lib/auth-storage';
import { clearTokenDispositivo, getTokenDispositivo } from '@/lib/biometria-storage';
import { consumirSesionVencida } from '@/lib/sesion-estado';
import { ApiError, NetworkError } from '@/services/api-client';
import {
  iniciarLogin,
  loginBiometrico,
  mensajeDeError,
  type SesionApp,
} from '@/services/auth-app';
import { biometriaDisponible, confirmarConBiometria } from '@/services/biometria-login';

const MAIL_VALIDO = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Mensaje para mostrar: si no hay conexión, indica a qué dirección se intentó llegar. */
function textoDeError(error: unknown, porDefecto: string): string {
  if (error instanceof NetworkError) {
    return `No se pudo conectar con el servidor (${API_URL}). Verificá que Laravel esté levantado.`;
  }

  return mensajeDeError(error, porDefecto);
}

export default function LoginScreen() {
  const tema = useSgcTheme();
  const router = useRouter();

  const [mail, setMail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [checkingSession, setCheckingSession] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [paso, setPaso] = useState<'credenciales' | 'mfa'>('credenciales');
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [biometriaLista, setBiometriaLista] = useState(false);

  useEffect(() => {
    const checkSession = async () => {
      try {
        const token = await getToken();
        if (token) {
          router.replace('/dashboard');
          return;
        }

        // Volvimos al login porque la API venció la sesión (10 minutos sin uso).
        if (consumirSesionVencida()) {
          setInfo('Tu sesión venció por inactividad. Ingresá de nuevo.');
        }

        const [hayBiometria, tokenDispositivo] = await Promise.all([
          biometriaDisponible(),
          getTokenDispositivo(),
        ]);
        setBiometriaLista(hayBiometria && tokenDispositivo !== null);
      } finally {
        setCheckingSession(false);
      }
    };

    checkSession();
  }, [router]);

  const canSubmit = mail.trim().length > 0 && password.length > 0 && !loading;

  async function abrirSesion(sesion: SesionApp) {
    await setToken(sesion.access_token);
    router.replace('/dashboard');
  }

  /** Paso 1: mail y contraseña. Si son correctos, la API pide el código (paso 2). */
  async function handleLogin() {
    if (!canSubmit) return;

    if (!MAIL_VALIDO.test(mail.trim())) {
      setError('Ingresá un correo electrónico válido.');
      return;
    }

    setLoading(true);
    setError(null);
    setInfo(null);

    try {
      const desafio = await iniciarLogin(mail.trim(), password);
      setMfaToken(desafio.mfa_token);
      setPaso('mfa');
    } catch (loginError) {
      setError(textoDeError(loginError, 'No se pudo iniciar sesión.'));
    } finally {
      setLoading(false);
    }
  }

  /** Ingreso con huella o rostro: reemplaza la contraseña y el código (el celular ya es el segundo factor). */
  async function handleBiometria() {
    if (loading) return;

    setLoading(true);
    setError(null);
    setInfo(null);

    try {
      const confirmada = await confirmarConBiometria('Ingresá al Sistema de Gestión de Convenios');
      if (!confirmada) return;

      const tokenDispositivo = await getTokenDispositivo();
      if (!tokenDispositivo) {
        setBiometriaLista(false);
        return;
      }

      await abrirSesion(await loginBiometrico(tokenDispositivo));
    } catch (biometriaError) {
      // La API ya no reconoce al dispositivo (revocado, vencido o usuario dado de baja).
      if (biometriaError instanceof ApiError && biometriaError.status === 401) {
        await clearTokenDispositivo();
        setBiometriaLista(false);
      }

      setError(textoDeError(biometriaError, 'No se pudo ingresar con biometría.'));
    } finally {
      setLoading(false);
    }
  }

  function volverAlLogin(mensaje?: string) {
    setPaso('credenciales');
    setMfaToken(null);
    setPassword('');
    setError(mensaje ?? null);
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

              {paso === 'mfa' && mfaToken ? (
                <>
                  <VerificacionMfa
                    mfaToken={mfaToken}
                    onVerificado={abrirSesion}
                    onCancelar={volverAlLogin}
                  />
                </>
              ) : (
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

                {info && (
                  <Txt variante="detalle" color="textSecondary" style={styles.info}>
                    {info}
                  </Txt>
                )}

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

                {biometriaLista && (
                  <Pressable
                    accessibilityRole="button"
                    onPress={handleBiometria}
                    disabled={loading}
                    style={({ pressed }) => [
                      styles.buttonOutline,
                      { borderColor: brand },
                      (loading || pressed) && styles.buttonDim,
                    ]}>
                    <Txt style={[styles.buttonOutlineText, { color: brandText }]}>
                      Ingresar con huella o rostro
                    </Txt>
                  </Pressable>
                )}

                <Pressable
                  accessibilityRole="button"
                  style={styles.forgotButton}
                  onPress={() => setError('La recuperación de contraseña estará disponible próximamente.') }>
                  <Txt style={[styles.forgotText, { color: brandText }]}>
                    Olvidé mi contraseña
                  </Txt>
                </Pressable>
              </View>
              )}
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
  buttonOutline: {
    height: 48,
    borderRadius: 6,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonOutlineText: {
    fontSize: 16,
    lineHeight: 22,
    fontWeight: '600',
  },
  info: {
    textAlign: 'center',
  },
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
