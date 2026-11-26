import { useRouter } from 'expo-router';
import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { VerificacionMfa } from '@/components/auth/verificacion-mfa';
import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { API_URL } from '@/constants/api';
import { AREA_TACTIL_MINIMA } from '@/constants/accesibilidad';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { anunciar } from '@/lib/accesibilidad';
import { getToken, setToken } from '@/lib/auth-storage';
import { consumirDestinoPendiente } from '@/lib/destino-pendiente';
import { asegurarCacheDelUsuario } from '@/lib/offline-storage';
import { clearTokenDispositivo, getTokenDispositivo } from '@/lib/biometria-storage';
import { consumirSesionVencida } from '@/lib/sesion-estado';
import { ApiError, NetworkError } from '@/services/api-client';
import {
  iniciarLogin,
  loginBiometrico,
  mensajeDeError,
  type SesionApp,
  type CanalMfa,
} from '@/services/auth-app';
import { biometriaDisponible, confirmarConBiometria } from '@/services/biometria-login';
import { registrarPushToken } from '@/services/push-token';

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
  // Con teclado físico, "Siguiente" en el correo salta a la contraseña.
  const passwordRef = useRef<TextInput>(null);

  const [mail, setMail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [checkingSession, setCheckingSession] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [info, setInfo] = useState<string | null>(null);
  const [paso, setPaso] = useState<'credenciales' | 'mfa'>('credenciales');
  const [mfaToken, setMfaToken] = useState<string | null>(null);
  const [canalesMfa, setCanalesMfa] = useState<CanalMfa[] | undefined>();
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

  // Errores y avisos se leen en voz alta cuando aparecen.
  useEffect(() => {
    anunciar(error);
  }, [error]);

  useEffect(() => {
    anunciar(info);
  }, [info]);

  const canSubmit = mail.trim().length > 0 && password.length > 0 && !loading;

  async function abrirSesion(sesion: SesionApp) {
    await setToken(sesion.access_token);

    // Si entra otra persona en este celular, no hereda el panel ni la cola de la anterior.
    try {
      await asegurarCacheDelUsuario(sesion.user.id_usuario);
    } catch (cacheError) {
      console.warn('No se pudo preparar el caché offline:', cacheError);
    }

    void registrarPushToken().catch((error) => {
      console.warn('No se pudo registrar el token push:', error);
    });

    router.replace('/dashboard');

    // Venía de tocar un aviso con la sesión vencida: ahora sí se abre.
    const destino = consumirDestinoPendiente();
    if (destino) router.push(destino);
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
      setCanalesMfa(desafio.canales);
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
    setCanalesMfa(undefined);
    setPassword('');
    setError(mensaje ?? null);
  }

  // Paleta de la app (misma que el resto de las pantallas), según la preferencia claro/oscuro.
  const { surface, border, page, brandText, brand } = tema;

  const inputStyle = [
    styles.input,
    { backgroundColor: surface, borderColor: border, color: tema.text, fontSize: 16 * tema.fontScale },
  ];

  if (checkingSession) {
    return (
      <View style={[styles.container, { backgroundColor: page }]}>
        <SafeAreaView style={styles.safeAreaCentered}>
          <ActivityIndicator size="large" color={brand} accessibilityLabel="Cargando" />
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
                {/* Repite el título de abajo: el lector de pantalla lo omite. */}
                <Txt
                  variante="detalle"
                  color="textSecondary"
                  accessibilityElementsHidden
                  importantForAccessibility="no"
                  style={styles.brandSubtext}>
                  Sistema de Gestión de Convenios
                </Txt>

                <Txt encabezado style={[styles.systemTitle, { color: tema.text }]}>
                  Sistema de Gestión de Convenios
                </Txt>
                <Txt encabezado style={[styles.loginTitle, { color: tema.text }]}>
                  Iniciar Sesión
                </Txt>
              </View>

              {paso === 'mfa' && mfaToken ? (
                <>
                  <VerificacionMfa
                    mfaToken={mfaToken}
                    canalesDisponibles={canalesMfa}
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
                    returnKeyType="next"
                    submitBehavior="submit"
                    onSubmitEditing={() => passwordRef.current?.focus()}
                    accessibilityLabel="Correo electrónico"
                    accessibilityHint="Escribí tu correo institucional"
                  />
                </View>

                <View style={styles.field}>
                  <Txt variante="detalle" negrita style={styles.label}>Contraseña</Txt>
                  <View>
                    <TextInput
                      ref={passwordRef}
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
                      accessibilityLabel="Contraseña"
                      accessibilityHint="Escribí tu contraseña"
                    />
                    <PressableA11y
                      accessibilityRole="switch"
                      accessibilityLabel="Mostrar contraseña"
                      accessibilityHint="Muestra u oculta lo que escribiste"
                      accessibilityState={{ checked: showPassword }}
                      style={styles.toggle}
                      hitSlop={{ top: 4, bottom: 4, left: 8, right: 8 }}
                      onPress={() => setShowPassword((visible) => !visible)}>
                      <Txt variante="detalle" color="textSecondary">
                        {showPassword ? 'Ocultar' : 'Mostrar'}
                      </Txt>
                    </PressableA11y>
                  </View>
                </View>

                {info && (
                  <Txt variante="detalle" color="textSecondary" style={styles.info}>
                    {info}
                  </Txt>
                )}

                {error && (
                  <Txt
                    variante="detalle"
                    accessibilityRole="alert"
                    style={[styles.error, { color: tema.danger }]}>
                    {error}
                  </Txt>
                )}

                <PressableA11y
                  accessibilityRole="button"
                  accessibilityHint="Comprueba tus datos y pasa a la verificación en dos pasos"
                  accessibilityState={{ disabled: !canSubmit, busy: loading }}
                  onPress={handleLogin}
                  disabled={!canSubmit}
                  style={({ pressed }) => [
                    styles.button,
                    { backgroundColor: brand },
                    (!canSubmit || pressed) && styles.buttonDim,
                  ]}>
                  {loading ? (
                    <ActivityIndicator color="#ffffff" accessibilityLabel="Iniciando sesión" />
                  ) : (
                    <Txt style={styles.buttonText}>Iniciar Sesión</Txt>
                  )}
                </PressableA11y>

                {biometriaLista && (
                  <PressableA11y
                    accessibilityRole="button"
                    accessibilityHint="Abre la sesión con la huella o el rostro registrados en este celular"
                    accessibilityState={{ disabled: loading }}
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
                  </PressableA11y>
                )}

                <PressableA11y
                  accessibilityRole="button"
                  accessibilityHint="Explica cómo recuperar la contraseña"
                  style={styles.forgotButton}
                  onPress={() => setError('Para recuperar tu contraseña, contactá al administrador del sistema.') }>
                  <Txt style={[styles.forgotText, { color: brandText }]}>
                    Olvidé mi contraseña
                  </Txt>
                </PressableA11y>
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
    minHeight: AREA_TACTIL_MINIMA,
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
