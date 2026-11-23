import { useState } from 'react';
import { Image } from 'expo-image';
import { Pressable, StyleSheet, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { ThemeToggle } from '@/components/panel/theme-toggle';
import { Txt } from '@/components/panel/txt';
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
        <View style={styles.marca}>
          <View style={[styles.logo, { backgroundColor: tema.brand }]}>
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

        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Abrir menú del perfil"
          onPress={onAbrirPerfil}
          style={({ pressed }) => [
            styles.avatar,
            { backgroundColor: tema.brand, borderColor: tema.onTopbar },
            pressed && { opacity: 0.8 },
          ]}>
          {fotoPerfil ? (
            <Image source={{ uri: fotoPerfil }} style={styles.avatarImagen} contentFit="cover" />
          ) : (
            <Txt variante="etiqueta" color="onBrand">
              {iniciales(nombreUsuario)}
            </Txt>
          )}
        </Pressable>
      </View>

      <View style={styles.fila}>
        <TextInput
          accessibilityLabel="Buscar convenios"
          editable={!sinConexion}
          value={texto}
          onChangeText={setTexto}
          onSubmitEditing={() => onBuscar(texto.trim())}
          returnKeyType="search"
          placeholder={sinConexion ? 'Búsqueda no disponible sin conexión' : 'Buscar convenios…'}
          placeholderTextColor={tema.fieldPlaceholder}
          style={[
            styles.buscador,
            { backgroundColor: tema.field, color: tema.onField },
            sinConexion && { opacity: 0.6 },
          ]}
        />
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Búsqueda avanzada con filtros"
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
        </Pressable>
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
    width: 38,
    height: 38,
    borderRadius: SgcRadius.pill,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarImagen: {
    width: '100%',
    height: '100%',
  },
  buscador: {
    flex: 1,
    height: 42,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.md,
    fontSize: 15,
    fontFamily: SgcFont,
  },
  filtros: {
    height: 42,
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
