import { useRouter } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Txt } from '@/components/panel/txt';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

type EncabezadoPantallaProps = {
  titulo: string;
  /** Por defecto vuelve atrás, o al panel si no hay historial. */
  onVolver?: () => void;
};

export function EncabezadoPantalla({ titulo, onVolver }: EncabezadoPantallaProps) {
  const tema = useSgcTheme();
  const router = useRouter();
  const insets = useSafeAreaInsets();

  const volver = onVolver ?? (() => (router.canGoBack() ? router.back() : router.replace('/dashboard')));

  return (
    <View
      style={[
        styles.encabezado,
        { backgroundColor: tema.topbar, paddingTop: insets.top + SgcSpacing.sm },
      ]}>
      <View style={styles.fila}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Volver"
          onPress={volver}
          hitSlop={8}
          style={({ pressed }) => pressed && { opacity: 0.6 }}>
          <Txt variante="subtitulo" color="onTopbar">
            ‹ Volver
          </Txt>
        </Pressable>
        <Txt variante="subtitulo" color="onTopbar" numberOfLines={1} style={styles.titulo}>
          {titulo}
        </Txt>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  encabezado: { paddingHorizontal: SgcSpacing.lg, paddingBottom: SgcSpacing.md },
  fila: { flexDirection: 'row', alignItems: 'center', gap: SgcSpacing.md },
  titulo: { flex: 1 },
});
