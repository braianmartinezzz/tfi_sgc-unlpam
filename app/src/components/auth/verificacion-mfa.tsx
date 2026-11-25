import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, TextInput, View } from 'react-native';

import { Txt } from '@/components/panel/txt';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { ApiError } from '@/services/api-client';
import {
  enviarCodigoMfa,
  mensajeDeError,
  verificarCodigoMfa,
  type CanalMfa,
  type EnvioCodigo,
  type SesionApp,
} from '@/services/auth-app';
import { obtenerExpoPushToken } from '@/services/push-token';

const LONGITUD_CODIGO = 6;

const CANALES: { canal: CanalMfa; titulo: string; detalle: string }[] = [
  { canal: 'mail', titulo: 'Correo electrónico', detalle: 'Te llega a tu casilla institucional' },
  { canal: 'sms', titulo: 'Mensaje de texto (SMS)', detalle: 'Al teléfono registrado' },
  { canal: 'push', titulo: 'Notificación push', detalle: 'A este celular' },
];

const NOMBRE_CANAL: Record<CanalMfa, string> = {
  mail: 'correo electrónico',
  sms: 'SMS',
  push: 'notificación push',
};

type Props = {
  mfaToken: string;
  canalesDisponibles?: CanalMfa[];
  onVerificado: (sesion: SesionApp) => void | Promise<void>;
  /** Volver al login (por decisión del usuario o porque el desafío ya no sirve). */
  onCancelar: (mensaje?: string) => void;
};

/** Segundo paso del login: elegir cómo recibir el código y escribirlo. */
export function VerificacionMfa({ mfaToken, canalesDisponibles, onVerificado, onCancelar }: Props) {
  const tema = useSgcTheme();

  const [envio, setEnvio] = useState<EnvioCodigo | null>(null);
  const [codigo, setCodigo] = useState('');
  const [enviandoPor, setEnviandoPor] = useState<CanalMfa | null>(null);
  const [verificando, setVerificando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const ocupado = enviandoPor !== null || verificando;
  const puedeVerificar = codigo.length === LONGITUD_CODIGO && !ocupado;

  /** Si el desafío venció o se agotaron los intentos, no queda nada por hacer acá. */
  function manejarError(causa: unknown, porDefecto: string) {
    if (causa instanceof ApiError && causa.message.includes('Volvé a iniciar sesión')) {
      onCancelar(causa.message);
      return;
    }

    setError(mensajeDeError(causa, porDefecto));
  }

  async function pedirCodigo(canal: CanalMfa) {
    if (ocupado) return;
    setEnviandoPor(canal);
    setError(null);

    try {
      let pushToken: string | null = null;

      if (canal === 'push') {
        pushToken = await obtenerExpoPushToken();

        if (!pushToken) {
          setError(
            'Este dispositivo no puede recibir notificaciones push (en Expo Go de Android no están disponibles). Elegí otro canal.',
          );
          return;
        }
      }

      setEnvio(await enviarCodigoMfa(mfaToken, canal, pushToken));
      setCodigo('');
    } catch (causa) {
      manejarError(causa, 'No se pudo enviar el código.');
    } finally {
      setEnviandoPor(null);
    }
  }

  async function verificar() {
    if (!puedeVerificar) return;
    setVerificando(true);
    setError(null);

    try {
      const sesion = await verificarCodigoMfa(mfaToken, codigo);
      await onVerificado(sesion);
    } catch (causa) {
      setCodigo('');
      manejarError(causa, 'No se pudo verificar el código.');
    } finally {
      setVerificando(false);
    }
  }

  const inputStyle = [
    styles.input,
    { backgroundColor: tema.surface, borderColor: tema.border, color: tema.text },
  ];

  const canales = canalesDisponibles?.length
    ? CANALES.filter(({ canal }) => canalesDisponibles.includes(canal))
    : CANALES;

  return (
    <View style={styles.contenedor}>
      <Txt style={[styles.titulo, { color: tema.text }]}>Verificación en dos pasos</Txt>

      {envio === null ? (
        <>
          <Txt variante="detalle" color="textSecondary" style={styles.centrado}>
            Elegí cómo querés recibir tu código de {LONGITUD_CODIGO} dígitos.
          </Txt>

          {canales.map(({ canal, titulo, detalle }) => (
            <Pressable
              key={canal}
              accessibilityRole="button"
              accessibilityLabel={`Recibir el código por ${NOMBRE_CANAL[canal]}`}
              disabled={ocupado}
              onPress={() => pedirCodigo(canal)}
              style={({ pressed }) => [
                styles.canal,
                { borderColor: tema.border, backgroundColor: tema.surface },
                (pressed || ocupado) && styles.atenuado,
              ]}>
              <View style={styles.canalTexto}>
                <Txt negrita>{titulo}</Txt>
                <Txt variante="detalle" color="textSecondary">
                  {detalle}
                </Txt>
              </View>
              {enviandoPor === canal ? <ActivityIndicator color={tema.brand} /> : null}
            </Pressable>
          ))}
        </>
      ) : (
        <>
          <Txt variante="detalle" color="textSecondary" style={styles.centrado}>
            Te enviamos un código por {NOMBRE_CANAL[envio.canal]} ({envio.destino}). Vence en{' '}
            {Math.round(envio.expira_en_segundos / 60)} minutos.
          </Txt>

          {envio.codigo_simulado ? (
            <View style={[styles.demo, { borderColor: tema.warning }]}>
              <Txt variante="detalle" color="textSecondary" style={styles.centrado}>
                Modo demostración (envío simulado)
              </Txt>
              <Txt negrita style={[styles.codigoDemo, { color: tema.text }]}>
                {envio.codigo_simulado}
              </Txt>
            </View>
          ) : null}

          <TextInput
            style={[inputStyle, styles.inputCodigo]}
            value={codigo}
            onChangeText={(texto) => setCodigo(texto.replace(/\D/g, '').slice(0, LONGITUD_CODIGO))}
            placeholder="••••••"
            placeholderTextColor={tema.textSecondary}
            keyboardType="number-pad"
            maxLength={LONGITUD_CODIGO}
            textContentType="oneTimeCode"
            autoComplete="sms-otp"
            autoFocus
            onSubmitEditing={verificar}
            returnKeyType="go"
            editable={!ocupado}
            accessibilityLabel="Código de verificación"
          />

          <Pressable
            accessibilityRole="button"
            onPress={verificar}
            disabled={!puedeVerificar}
            style={({ pressed }) => [
              styles.boton,
              { backgroundColor: tema.brand },
              (!puedeVerificar || pressed) && styles.atenuado,
            ]}>
            {verificando ? (
              <ActivityIndicator color="#ffffff" />
            ) : (
              <Txt style={styles.textoBoton}>Verificar</Txt>
            )}
          </Pressable>

          <Pressable
            accessibilityRole="button"
            disabled={ocupado}
            onPress={() => {
              setEnvio(null);
              setCodigo('');
              setError(null);
            }}
            style={styles.enlace}>
            <Txt variante="detalle" style={{ color: tema.brandText }}>
              No me llegó: pedir otro código
            </Txt>
          </Pressable>
        </>
      )}

      {error ? (
        <Txt variante="detalle" style={styles.error}>
          {error}
        </Txt>
      ) : null}

      <Pressable
        accessibilityRole="button"
        disabled={ocupado}
        onPress={() => onCancelar()}
        style={styles.enlace}>
        <Txt variante="detalle" color="textSecondary">
          Volver al inicio de sesión
        </Txt>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  contenedor: { marginTop: 24, gap: 12 },
  titulo: { fontSize: 20, lineHeight: 26, fontWeight: '500', textAlign: 'center' },
  centrado: { textAlign: 'center' },
  canal: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    borderWidth: 1,
    borderRadius: 6,
    paddingHorizontal: 14,
    paddingVertical: 12,
    minHeight: 56,
  },
  canalTexto: { flex: 1, gap: 2 },
  demo: {
    borderWidth: 1,
    borderStyle: 'dashed',
    borderRadius: 6,
    paddingVertical: 8,
    alignItems: 'center',
  },
  codigoDemo: { fontSize: 24, lineHeight: 30, letterSpacing: 6 },
  input: {
    height: 48,
    borderRadius: 6,
    borderWidth: 1,
    paddingHorizontal: 14,
    fontSize: 16,
  },
  inputCodigo: { textAlign: 'center', fontSize: 22, letterSpacing: 8 },
  boton: {
    height: 48,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  textoBoton: { color: '#ffffff', fontSize: 16, lineHeight: 22, fontWeight: '600' },
  atenuado: { opacity: 0.65 },
  enlace: { alignItems: 'center', minHeight: 32, justifyContent: 'center' },
  error: { color: '#D93025', textAlign: 'center' },
});
