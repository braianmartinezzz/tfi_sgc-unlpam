import { Modal, Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import type { UsuarioAutenticado } from '@/types/auth';

type Opcion = {
  clave: string;
  texto: string;
  /** Sin handler la opción se muestra deshabilitada como "Próximamente". */
  onPress?: () => void;
  peligrosa?: boolean;
};

type ProfileMenuProps = {
  visible: boolean;
  usuario: UsuarioAutenticado | null;
  onCerrar: () => void;
  onMiPerfil?: () => void;
  onRegistrarIdentidad?: () => void;
  onAyuda?: () => void;
  onCerrarSesion: () => void;
};

export function ProfileMenu({
  visible,
  usuario,
  onCerrar,
  onMiPerfil,
  onRegistrarIdentidad,
  onAyuda,
  onCerrarSesion,
}: ProfileMenuProps) {
  const tema = useSgcTheme();
  const insets = useSafeAreaInsets();

  const opciones: Opcion[] = [
    { clave: 'perfil', texto: 'Mi perfil', onPress: onMiPerfil },
    { clave: 'identidad', texto: 'Registrar mi identidad', onPress: onRegistrarIdentidad },
    { clave: 'ayuda', texto: 'Ayuda y manual de usuario', onPress: onAyuda },
    { clave: 'salir', texto: 'Cerrar sesión', onPress: onCerrarSesion, peligrosa: true },
  ];

  return (
    <Modal transparent animationType="fade" visible={visible} onRequestClose={onCerrar}>
      <Pressable accessibilityLabel="Cerrar menú" style={styles.fondo} onPress={onCerrar}>
        <Pressable
          style={[
            styles.menu,
            {
              backgroundColor: tema.surface,
              borderColor: tema.border,
              marginTop: insets.top + 56,
            },
          ]}
          // Evita que tocar dentro del menú lo cierre.
          onPress={() => undefined}>
          <View style={[styles.cabecera, { borderBottomColor: tema.border }]}>
            <Txt variante="subtitulo">{usuario?.nombre ?? 'Usuario'}</Txt>
            <Txt variante="detalle" color="textSecondary">
              {[usuario?.rol?.nombre, usuario?.unidad_academica?.sigla].filter(Boolean).join(' · ')}
            </Txt>
            <Txt variante="detalle" color="textSecondary">
              {usuario?.mail}
            </Txt>
          </View>

          {opciones.map((opcion) => (
            <Pressable
              key={opcion.clave}
              accessibilityRole="button"
              accessibilityState={{ disabled: !opcion.onPress }}
              disabled={!opcion.onPress}
              onPress={opcion.onPress}
              style={({ pressed }) => [
                styles.opcion,
                pressed && { backgroundColor: tema.rowAlternate },
                !opcion.onPress && { opacity: 0.5 },
              ]}>
              <Txt negrita style={opcion.peligrosa ? { color: tema.danger } : undefined}>
                {opcion.texto}
              </Txt>
              {!opcion.onPress ? (
                <Txt variante="etiqueta" color="textSecondary">
                  Próximamente
                </Txt>
              ) : null}
            </Pressable>
          ))}
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  fondo: {
    flex: 1,
    alignItems: 'flex-end',
    paddingHorizontal: SgcSpacing.lg,
    backgroundColor: 'rgba(0,0,0,0.35)',
  },
  menu: {
    width: 280,
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    overflow: 'hidden',
  },
  cabecera: {
    padding: SgcSpacing.lg,
    gap: 2,
    borderBottomWidth: 1,
  },
  opcion: {
    paddingHorizontal: SgcSpacing.lg,
    paddingVertical: SgcSpacing.md,
  },
});
