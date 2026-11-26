import { useState } from 'react';
import { Image } from 'expo-image';
import { StyleSheet, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { PressableA11y } from '@/components/panel/pressable-a11y';
import { ThemeToggle } from '@/components/panel/theme-toggle';
import { Txt } from '@/components/panel/txt';
import { AREA_TACTIL_MINIMA } from '@/constants/accesibilidad';
import { SgcFont, SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useProfilePhoto } from '@/hooks/use-profile-photo';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { iniciales } from '@/lib/format';

type PanelHeaderProps = {
  nombreUsuario: string | null;
  /** URL de la foto de perfil; si no hay, se muestran las iniciales. */
  fotoUrl?: string | null;
  sinConexion: boolean;
  onBuscar: (texto: string) => void;
  onBusquedaAvanzada: () => void;
  onAbrirPerfil: () => void;
};

export function PanelHeader({
  nombreUsuario,
  sinConexion,
  onBuscar,
  onBusquedaAvanzada,
  onAbrirPerfil,
}: PanelHeaderProps) {
  const tema = useSgcTheme();
  const insets = useSafeAreaInsets();
  const [texto, setTexto] = useState('');
  const fotoPerfil = useProfilePhoto();

  return (
    <View
      style={[
        styles.contenedor,
        { backgroundColor: tema.topbar, paddingTop: insets.top + SgcSpacing.sm },
      ]}>
      <View style={styles.fila}>
        {/* Marca: un solo elemento para el lector de pantalla (el logo es decorativo). */}
        <View
          accessible
          accessibilityRole="header"
          accessibilityLabel="UNLPam, Sistema de Convenios"
          style={styles.marca}>
          <View
            importantForAccessibility="no-hide-descendants"
            accessibilityElementsHidden
            style={[styles.logo, { backgroundColor: tema.brand }]}>
            <View style={[styles.logoLineaSuperior, { backgroundColor: tema.onBrand }]} />
            <Txt variante="etiqueta" color="onBrand" negrita>
              SGC
            </Txt>
          </View>
          <View>
            <Txt variante="subtitulo" color="onTopbar">
              UNLPam
            </Txt>
            <Txt variante="etiqueta" color="onTopbar" style={styles.subMarca}>
              Sistema de Convenios
            </Txt>
          </View>
        </View>

        <ThemeToggle />

        <PressableA11y
          accessibilityRole="button"
          accessibilityLabel="Abrir menú del perfil"
          accessibilityHint="Muestra Mi perfil, Ayuda y Cerrar sesión"
          onPress={onAbrirPerfil}
          style={({ pressed }) => [
            styles.avatar,
            { backgroundColor: tema.brand, borderColor: tema.onTopbar },
            pressed && { opacity: 0.8 },
          ]}>
          {fotoPerfil ? (
            <Image
              source={{ uri: fotoPerfil }}
              style={styles.avatarImagen}
              contentFit="cover"
              accessible={false}
            />
          ) : (
            <Txt variante="etiqueta" color="onBrand">
              {iniciales(nombreUsuario)}
            </Txt>
          )}
        </PressableA11y>
      </View>

      <View style={styles.fila}>
        <TextInput
          accessibilityLabel="Buscar convenios"
          accessibilityHint="Escribí parte del título y tocá Buscar en el teclado"
          editable={!sinConexion}
          value={texto}
          onChangeText={setTexto}
          onSubmitEditing={() => onBuscar(texto.trim())}
          returnKeyType="search"
          placeholder={sinConexion ? 'Búsqueda no disponible sin conexión' : 'Buscar convenios…'}
          placeholderTextColor={tema.fieldPlaceholder}
          style={[
            styles.buscador,
            { backgroundColor: tema.field, color: tema.onField, fontSize: 15 * tema.fontScale },
            sinConexion && { opacity: 0.6 },
          ]}
        />
        <PressableA11y
          accessibilityRole="button"
          accessibilityLabel="Búsqueda avanzada con filtros"
          accessibilityHint="Abre la búsqueda por estado, tipo y unidad académica"
          accessibilityState={{ disabled: sinConexion }}
          disabled={sinConexion}
          onPress={onBusquedaAvanzada}
          style={({ pressed }) => [
            styles.filtros,
            { borderColor: tema.fieldPlaceholder },
            pressed && { opacity: 0.7 },
            sinConexion && { opacity: 0.4 },
          ]}>
          <Txt variante="detalle" color="onTopbar" negrita>
            Filtros
          </Txt>
        </PressableA11y>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  contenedor: {
    paddingHorizontal: SgcSpacing.lg,
    paddingBottom: SgcSpacing.md,
    gap: SgcSpacing.md,
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SgcSpacing.sm,
  },
  marca: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: SgcSpacing.sm,
  },
  logo: {
    width: 34,
    height: 34,
    borderRadius: SgcRadius.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoLineaSuperior: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 2,
    borderTopLeftRadius: SgcRadius.sm,
    borderTopRightRadius: SgcRadius.sm,
  },
  subMarca: {
    opacity: 0.7,
  },
  avatar: {
    width: AREA_TACTIL_MINIMA,
    height: AREA_TACTIL_MINIMA,
    borderRadius: SgcRadius.pill,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  avatarImagen: {
    width: '100%',
    height: '100%',
  },
  buscador: {
    flex: 1,
    height: AREA_TACTIL_MINIMA,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.md,
    fontFamily: SgcFont,
  },
  filtros: {
    height: AREA_TACTIL_MINIMA,
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
