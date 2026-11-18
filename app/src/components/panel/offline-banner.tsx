import { StyleSheet, View } from 'react-native';

import { Txt } from '@/components/panel/txt';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatFecha, formatHora } from '@/lib/format';

export function OfflineBanner({ actualizado }: { actualizado: Date | null }) {
  const tema = useSgcTheme();

  return (
    <View
      accessibilityRole="alert"
      style={[styles.banner, { backgroundColor: tema.warning }]}>
      <Txt variante="subtitulo" style={styles.texto}>
        Sin conexión (modo offline)
      </Txt>
      <Txt variante="detalle" style={styles.texto}>
        Solo se pueden ver los convenios pendientes de firma guardados en este dispositivo.
        La búsqueda, los comentarios y las firmas no están disponibles hasta recuperar la conexión.
      </Txt>
      {actualizado ? (
        <Txt variante="etiqueta" style={styles.texto}>
          Última actualización: {formatFecha(actualizado.toISOString())} {formatHora(actualizado)}
        </Txt>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  banner: {
    paddingHorizontal: SgcSpacing.lg,
    paddingVertical: SgcSpacing.md,
    gap: 2,
  },
  texto: {
    color: '#1a1a1a',
  },
});
