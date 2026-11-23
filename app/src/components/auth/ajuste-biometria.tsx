import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  StyleSheet,
  Switch,
  TextInput,
  View,
} from 'react-native';

import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import {
  clearTokenDispositivo,
  getTokenDispositivo,
  setTokenDispositivo,
} from '@/lib/biometria-storage';
import { ApiError } from '@/services/api-client';
import { activarBiometria, desactivarBiometria, mensajeDeError } from '@/services/auth-app';
import { biometriaDisponible, confirmarConBiometria } from '@/services/biometria-login';

/** Opción de "Mi perfil": ingresar con huella o rostro en lugar de la contraseña y el código. */
export function AjusteBiometria() {
  const tema = useSgcTheme();

  const [disponible, setDisponible] = useState<boolean | null>(null);
  const [activa, setActiva] = useState(false);
  const [modalAbierto, setModalAbierto] = useState(false);
  const [password, setPassword] = useState('');
  const [trabajando, setTrabajando] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let vigente = true;

    (async () => {
      const [hayBiometria, token] = await Promise.all([biometriaDisponible(), getTokenDispositivo()]);

      if (vigente) {
        setDisponible(hayBiometria);
        setActiva(hayBiometria && token !== null);
      }
    })();

    return () => {
      vigente = false;
    };
  }, []);

  function cerrarModal() {
    if (trabajando) return;
    setModalAbierto(false);
    setPassword('');
    setError(null);
  }

  async function activar() {
    if (password.length === 0 || trabajando) return;
    setTrabajando(true);
    setError(null);

    try {
      const confirmada = await confirmarConBiometria('Confirmá tu identidad para activar el ingreso con biometría');

      if (!confirmada) {
        setError('No se pudo verificar tu huella o rostro.');
        return;
      }

      await setTokenDispositivo(await activarBiometria(password));
      setActiva(true);
      setModalAbierto(false);
      setPassword('');
    } catch (causa) {
      setError(mensajeDeError(causa, 'No se pudo activar el ingreso con biometría.'));
    } finally {
      setTrabajando(false);
    }
  }

  async function desactivar() {
    setTrabajando(true);

    try {
      const token = await getTokenDispositivo();
      let sinRevocar = false;

      if (token) {
        try {
          await desactivarBiometria(token);
        } catch (causa) {
          // Token ya vencido o revocado: no hay nada que revocar. Otro error: se avisa abajo.
          sinRevocar = !(causa instanceof ApiError && causa.status === 401);
        }
      }

      await clearTokenDispositivo();
      setActiva(false);

      if (sinRevocar) {
        Alert.alert(
          'Desactivado en este dispositivo',
          'No hubo conexión con el servidor para revocar el acceso. Se revocará la próxima vez que abras la sesión.',
        );
      }
    } finally {
      setTrabajando(false);
    }
  }

  function alternar(valor: boolean) {
    if (valor) {
      setModalAbierto(true);
    } else {
      void desactivar();
    }
  }

  if (disponible === null) {
    return <ActivityIndicator color={tema.brand} />;
  }

  return (
    <View style={styles.contenedor}>
      <View style={styles.fila}>
        <View style={styles.texto}>
          <Txt negrita>Ingresar con huella o rostro</Txt>
          <Txt variante="detalle" color="textSecondary">
            {disponible
              ? 'Entrás sin escribir la contraseña ni el código de verificación.'
              : 'Este dispositivo no tiene huella o rostro configurados.'}
          </Txt>
        </View>
        <Switch
          accessibilityLabel="Ingresar con huella o rostro"
          value={activa}
          disabled={!disponible || trabajando}
          onValueChange={alternar}
          trackColor={{ true: tema.brand, false: tema.border }}
        />
      </View>

      <Modal visible={modalAbierto} transparent animationType="fade" onRequestClose={cerrarModal}>
        <View style={styles.fondo}>
          <View style={[styles.modal, { backgroundColor: tema.surface, borderColor: tema.border }]}>
            <Txt variante="subtitulo">Activar ingreso con biometría</Txt>
            <Txt variante="detalle" color="textSecondary">
              Confirmá tu contraseña. Después te vamos a pedir tu huella o rostro.
            </Txt>

            <TextInput
              style={[
                styles.input,
                { backgroundColor: tema.surface, borderColor: tema.border, color: tema.text },
              ]}
              value={password}
              onChangeText={setPassword}
              placeholder="Contraseña"
              placeholderTextColor={tema.textSecondary}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              autoFocus
              editable={!trabajando}
              onSubmitEditing={activar}
              returnKeyType="go"
              accessibilityLabel="Contraseña"
            />

            {error ? (
              <Txt variante="detalle" style={styles.error}>
                {error}
              </Txt>
            ) : null}

            <View style={styles.botones}>
              <Pressable
                accessibilityRole="button"
                disabled={trabajando}
                onPress={cerrarModal}
                style={({ pressed }) => [
                  styles.boton,
                  { borderColor: tema.border },
                  pressed && styles.atenuado,
                ]}>
                <Txt negrita>Cancelar</Txt>
              </Pressable>

              <Pressable
                accessibilityRole="button"
                disabled={password.length === 0 || trabajando}
                onPress={activar}
                style={({ pressed }) => [
                  styles.boton,
                  { borderColor: tema.brand, backgroundColor: tema.brand },
                  (pressed || password.length === 0 || trabajando) && styles.atenuado,
                ]}>
                {trabajando ? (
                  <ActivityIndicator color={tema.onBrand} />
                ) : (
                  <Txt negrita style={{ color: tema.onBrand }}>
                    Activar
                  </Txt>
                )}
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  contenedor: { gap: SgcSpacing.md },
  fila: { flexDirection: 'row', alignItems: 'center', gap: SgcSpacing.md },
  texto: { flex: 1, gap: 2 },
  fondo: {
    flex: 1,
    justifyContent: 'center',
    padding: SgcSpacing.lg,
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  modal: {
    width: '100%',
    maxWidth: 420,
    alignSelf: 'center',
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    padding: SgcSpacing.lg,
    gap: SgcSpacing.md,
  },
  input: {
    height: 48,
    borderRadius: SgcRadius.md,
    borderWidth: 1,
    paddingHorizontal: 14,
    fontSize: 16,
  },
  error: { color: '#D93025' },
  botones: { flexDirection: 'row', gap: SgcSpacing.sm },
  boton: {
    flex: 1,
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
  },
  atenuado: { opacity: 0.6 },
});
