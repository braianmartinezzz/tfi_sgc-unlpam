import { useRouter } from 'expo-router';
import { StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { AREA_TACTIL_MINIMA } from '@/constants/accesibilidad';
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
        <PressableA11y
          accessibilityRole="button"
          accessibilityLabel="Volver"
          accessibilityHint="Vuelve a la pantalla anterior"
          onPress={volver}
          hitSlop={8}
          style={({ pressed }) => [styles.volver, pressed && { opacity: 0.6 }]}>
          <Txt variante="subtitulo" color="onTopbar">
            ‹ Volver
          </Txt>
        </PressableA11y>
        <Txt encabezado variante="subtitulo" color="onTopbar" numberOfLines={1} style={styles.titulo}>
          {titulo}
        </Txt>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  encabezado: { paddingHorizontal: SgcSpacing.lg, paddingBottom: SgcSpacing.md },
  fila: { flexDirection: 'row', alignItems: 'center', gap: SgcSpacing.md },
  volver: { minHeight: AREA_TACTIL_MINIMA, justifyContent: 'center' },
  titulo: { flex: 1 },
});
