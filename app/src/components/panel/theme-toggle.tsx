import { Pressable, StyleSheet, View } from 'react-native';

import { SgcRadius } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

const ICONO = 16;
const RAYOS = [0, 45, 90, 135, 180, 225, 270, 315];

/** Sol: núcleo lleno con ocho rayos cortos y separados (sin cruzar el centro). */
function IconoSol({ color }: { color: string }) {
  return (
    <View style={styles.icono}>
      <View style={[styles.solNucleo, { backgroundColor: color }]} />
      {RAYOS.map((grados) => (
        <View key={grados} style={[styles.solRayoContenedor, { transform: [{ rotate: `${grados}deg` }] }]}>
          <View style={[styles.solRayo, { backgroundColor: color }]} />
        </View>
      ))}
    </View>
  );
}

/** Luna creciente: disco lleno al que se le "muerde" una parte con el color del fondo. */
function IconoLuna({ color, fondo }: { color: string; fondo: string }) {
  return (
    <View style={[styles.icono, styles.lunaCaja]}>
      <View style={[styles.lunaDisco, { backgroundColor: color }]} />
      <View style={[styles.lunaCorte, { backgroundColor: fondo }]} />
    </View>
  );
}

/**
 * Interruptor claro/oscuro con forma de píldora: la mitad activa se rellena con el rojo
 * institucional, igual que los chips de filtros. Un toque en cualquier parte alterna el tema.
 */
export function ThemeToggle() {
  const tema = useSgcTheme();
  const oscuro = tema.scheme === 'dark';

  return (
    <Pressable
      accessibilityRole="switch"
      accessibilityLabel="Modo oscuro"
      accessibilityState={{ checked: oscuro }}
      hitSlop={6}
      onPress={() => tema.setPreference(oscuro ? 'light' : 'dark')}
      style={({ pressed }) => [
        styles.pildora,
        { backgroundColor: tema.field, borderColor: tema.fieldPlaceholder },
        pressed && { opacity: 0.8 },
      ]}>
      <View
        pointerEvents="none"
        style={[styles.segmento, !oscuro && { backgroundColor: tema.brand }]}>
        <IconoSol color={!oscuro ? tema.onBrand : tema.fieldPlaceholder} />
      </View>
      <View
        pointerEvents="none"
        style={[styles.segmento, oscuro && { backgroundColor: tema.brand }]}>
        <IconoLuna
          color={oscuro ? tema.onBrand : tema.fieldPlaceholder}
          fondo={oscuro ? tema.brand : tema.field}
        />
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pildora: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: SgcRadius.pill,
    padding: 2,
    gap: 2,
  },
  segmento: {
    width: 28,
    height: 24,
    borderRadius: SgcRadius.pill,
    alignItems: 'center',
    justifyContent: 'center',
  },
  icono: {
    width: ICONO,
    height: ICONO,
    alignItems: 'center',
    justifyContent: 'center',
  },
  solNucleo: {
    width: 6,
    height: 6,
    borderRadius: SgcRadius.pill,
  },
  solRayoContenedor: {
    position: 'absolute',
    width: ICONO,
    height: ICONO,
    alignItems: 'center',
  },
  solRayo: {
    width: 1.5,
    height: 3,
    borderRadius: 1,
  },
  lunaCaja: {
    overflow: 'hidden',
  },
  lunaDisco: {
    position: 'absolute',
    width: 13,
    height: 13,
    left: 1.5,
    top: 2.5,
    borderRadius: SgcRadius.pill,
  },
  lunaCorte: {
    position: 'absolute',
    width: 11,
    height: 11,
    left: 6,
    top: 0.5,
    borderRadius: SgcRadius.pill,
  },
});
