import { useLocalSearchParams, useRouter } from 'expo-router';
import { Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

/**
 * Pantalla provisoria para los accesos del panel cuyo destino todavía no existe.
 * Cada destino se reemplaza cambiando la ruta en constants/navigation.ts.
 */
export default function ProximamenteScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const { titulo, detalle } = useLocalSearchParams<{ titulo?: string; detalle?: string }>();

  return (
    <SafeAreaView style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <View style={[styles.tarjeta, { backgroundColor: tema.surface, borderColor: tema.border }]}>
        <Txt variante="titulo">{titulo ?? 'Próximamente'}</Txt>
        {detalle ? (
          <Txt color="textSecondary">{detalle}</Txt>
        ) : null}
        <Txt color="textSecondary">Esta sección todavía no está disponible en la aplicación.</Txt>

        <Pressable
          accessibilityRole="button"
          onPress={() => (router.canGoBack() ? router.back() : router.replace('/dashboard'))}
          style={({ pressed }) => [
            styles.boton,
            { backgroundColor: tema.brand },
            pressed && { opacity: 0.85 },
          ]}>
          <Txt negrita color="onBrand">
            Volver al panel
          </Txt>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  pantalla: {
    flex: 1,
    justifyContent: 'center',
    padding: SgcSpacing.lg,
  },
  tarjeta: {
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    padding: SgcSpacing.xl,
    gap: SgcSpacing.md,
    width: '100%',
    maxWidth: 520,
    alignSelf: 'center',
  },
  boton: {
    marginTop: SgcSpacing.sm,
    borderRadius: SgcRadius.md,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
  },
});
