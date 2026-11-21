import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { Card } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { usePanel } from '@/hooks/use-panel';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { iniciales } from '@/lib/format';
import { cerrarSesion } from '@/services/auth';

export default function PerfilScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const { me, estado } = usePanel();
  const [saliendo, setSaliendo] = useState(false);

  const confirmarSalida = () => {
    Alert.alert('Cerrar sesión', '¿Querés cerrar la sesión en este dispositivo?', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Cerrar sesión',
        style: 'destructive',
        onPress: async () => {
          setSaliendo(true);
          await cerrarSesion();
          router.replace('/');
        },
      },
    ]);
  };

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Mi perfil" />

      <ScrollView contentContainerStyle={styles.contenido}>
        <View style={styles.columna}>
          {!me && estado === 'cargando' ? (
            <View style={styles.centrado}>
              <ActivityIndicator color={tema.brand} />
            </View>
          ) : null}

          {me ? (
            <Card bordeSuperior={tema.brand}>
              <View style={styles.cabecera}>
                <View style={[styles.avatar, { backgroundColor: tema.brand }]}>
                  <Txt variante="subtitulo" color="onBrand">
                    {iniciales(me.nombre)}
                  </Txt>
                </View>
                <View style={styles.cabeceraTexto}>
                  <Txt variante="subtitulo">{me.nombre}</Txt>
                  <Txt variante="detalle" color="textSecondary">
                    {me.mail}
                  </Txt>
                </View>
              </View>

              <Dato etiqueta="Rol" valor={me.rol?.nombre ?? null} />
              <Dato
                etiqueta="Unidad académica"
                valor={
                  me.unidad_academica
                    ? `${me.unidad_academica.nombre} (${me.unidad_academica.sigla})`
                    : null
                }
              />
              <Dato
                etiqueta="Alcance"
                valor={
                  me.alcance_global === undefined
                    ? null
                    : me.alcance_global
                      ? 'Todas las unidades académicas'
                      : 'Solo tu unidad académica'
                }
              />
            </Card>
          ) : null}

          <Card titulo="Mi identidad">
            <Txt variante="detalle" color="textSecondary">
              Registrá tu firma, tu reconocimiento facial y tu huella digital en este dispositivo.
            </Txt>
            <Pressable
              accessibilityRole="button"
              onPress={() => router.push('/identidad')}
              style={({ pressed }) => [
                styles.opcion,
                { borderColor: tema.brand },
                pressed && { opacity: 0.8 },
              ]}>
              <Txt negrita style={{ color: tema.brandText }}>
                Registrar mi identidad
              </Txt>
            </Pressable>
          </Card>

          <Card titulo="Apariencia">
            <View style={styles.opciones}>
              <Opcion
                texto="Claro"
                activa={tema.scheme === 'light'}
                onPress={() => tema.setPreference('light')}
              />
              <Opcion
                texto="Oscuro"
                activa={tema.scheme === 'dark'}
                onPress={() => tema.setPreference('dark')}
              />
            </View>
          </Card>

          <Pressable
            accessibilityRole="button"
            disabled={saliendo}
            onPress={confirmarSalida}
            style={({ pressed }) => [
              styles.salir,
              { borderColor: tema.danger },
              (pressed || saliendo) && { opacity: 0.6 },
            ]}>
            {saliendo ? (
              <ActivityIndicator color={tema.danger} />
            ) : (
              <Txt negrita style={{ color: tema.danger }}>
                Cerrar sesión
              </Txt>
            )}
          </Pressable>
        </View>
      </ScrollView>
    </View>
  );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
  if (!valor) return null;

  return (
    <View style={styles.dato}>
      <Txt variante="etiqueta" color="textSecondary">
        {etiqueta}
      </Txt>
      <Txt>{valor}</Txt>
    </View>
  );
}

function Opcion({ texto, activa, onPress }: { texto: string; activa: boolean; onPress: () => void }) {
  const tema = useSgcTheme();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: activa }}
      onPress={onPress}
      style={({ pressed }) => [
        styles.opcion,
        {
          borderColor: activa ? tema.brand : tema.border,
          backgroundColor: activa ? tema.brand : 'transparent',
        },
        pressed && { opacity: 0.8 },
      ]}>
      <Txt negrita style={{ color: activa ? tema.onBrand : tema.text }}>
        {texto}
      </Txt>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  contenido: { padding: SgcSpacing.lg, paddingBottom: SgcSpacing.xl * 2 },
  columna: { width: '100%', maxWidth: 800, alignSelf: 'center', gap: SgcSpacing.lg },
  centrado: { alignItems: 'center', padding: SgcSpacing.xl },
  cabecera: { flexDirection: 'row', alignItems: 'center', gap: SgcSpacing.md },
  cabeceraTexto: { flex: 1, gap: 2 },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: SgcRadius.pill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dato: { gap: 2 },
  opciones: { flexDirection: 'row', gap: SgcSpacing.sm },
  opcion: {
    flex: 1,
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
  },
  salir: {
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
  },
});
