import { Modal, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { AREA_TACTIL_MINIMA } from '@/constants/accesibilidad';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import type { UsuarioAutenticado } from '@/types/auth';

type Opcion = {
  clave: string;
  texto: string;
  /** Qué hace la opción; lo lee el lector de pantalla. */
  pista: string;
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
    {
      clave: 'perfil',
      texto: 'Mi perfil',
      pista: 'Abre tus datos, la apariencia y el tamaño de letra',
      onPress: onMiPerfil,
    },
    {
      clave: 'identidad',
      texto: 'Registrar mi identidad',
      pista: 'Registra tu firma, tu rostro y tu huella en este dispositivo',
      onPress: onRegistrarIdentidad,
    },
    {
      clave: 'ayuda',
      texto: 'Ayuda y manual de usuario',
      pista: 'Abre la guía de uso de la aplicación',
      onPress: onAyuda,
    },
    {
      clave: 'salir',
      texto: 'Cerrar sesión',
      pista: 'Cierra la sesión en este dispositivo',
      onPress: onCerrarSesion,
      peligrosa: true,
    },
  ];

  return (
    <Modal transparent animationType="fade" visible={visible} onRequestClose={onCerrar}>
      <PressableA11y
        accessibilityRole="button"
        accessibilityLabel="Cerrar menú"
        accessibilityHint="Vuelve al panel sin elegir ninguna opción"
        style={styles.fondo}
        onPress={onCerrar}>
        <View
          accessibilityViewIsModal
          style={[
            styles.menu,
            {
              backgroundColor: tema.surface,
              borderColor: tema.border,
              marginTop: insets.top + 56,
            },
          ]}
          // Evita que tocar dentro del menú lo cierre.
          onStartShouldSetResponder={() => true}>
          <View style={[styles.cabecera, { borderBottomColor: tema.border }]}>
            <Txt encabezado variante="subtitulo">
              {usuario?.nombre ?? 'Usuario'}
            </Txt>
            <Txt variante="detalle" color="textSecondary">
              {[usuario?.rol?.nombre, usuario?.unidad_academica?.sigla].filter(Boolean).join(' · ')}
            </Txt>
            <Txt variante="detalle" color="textSecondary">
              {usuario?.mail}
            </Txt>
          </View>

          {opciones.map((opcion) => (
            <PressableA11y
              key={opcion.clave}
              accessibilityRole="button"
              accessibilityLabel={opcion.onPress ? opcion.texto : `${opcion.texto}, próximamente`}
              accessibilityHint={opcion.onPress ? opcion.pista : undefined}
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
            </PressableA11y>
          ))}
        </View>
      </PressableA11y>
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
    minHeight: AREA_TACTIL_MINIMA,
    justifyContent: 'center',
    paddingHorizontal: SgcSpacing.lg,
    paddingVertical: SgcSpacing.md,
  },
});
