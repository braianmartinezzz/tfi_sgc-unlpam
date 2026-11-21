import { type ReactNode } from 'react';
import { Pressable, StyleSheet, View, type StyleProp, type ViewStyle } from 'react-native';

import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

type CardProps = {
  titulo?: string;
  /** Texto o elemento a la derecha del título (por ejemplo un contador). */
  acento?: ReactNode;
  /** Color del borde superior, como en las cajas de resumen. */
  bordeSuperior?: string;
  children: ReactNode;
  style?: StyleProp<ViewStyle>;
};

export function Card({ titulo, acento, bordeSuperior, children, style }: CardProps) {
  const tema = useSgcTheme();

  return (
    <View
      style={[
        styles.card,
        { backgroundColor: tema.surface, borderColor: tema.border },
        bordeSuperior ? { borderTopColor: bordeSuperior, borderTopWidth: 3 } : null,
        style,
      ]}>
      {titulo ? (
        <View style={styles.encabezado}>
          <Txt variante="subtitulo">{titulo}</Txt>
          {acento}
        </View>
      ) : null}
      {children}
    </View>
  );
}

type EnlaceProps = {
  texto: string;
  onPress: () => void;
  deshabilitado?: boolean;
};

/** Texto accionable en el color institucional, usado como "Ver todos". */
export function Enlace({ texto, onPress, deshabilitado }: EnlaceProps) {
  const tema = useSgcTheme();

  return (
    <Pressable
      accessibilityRole="button"
      disabled={deshabilitado}
      onPress={onPress}
      hitSlop={8}
      style={({ pressed }) => [pressed && { opacity: 0.6 }, deshabilitado && { opacity: 0.4 }]}>
      <Txt variante="detalle" negrita style={{ color: tema.brandText }}>
        {texto}
      </Txt>
    </Pressable>
  );
}

export function EstadoVacio({ texto }: { texto: string }) {
  return (
    <View style={styles.vacio}>
      <Txt variante="detalle" color="textSecondary">
        {texto}
      </Txt>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    padding: SgcSpacing.lg,
    gap: SgcSpacing.md,
  },
  encabezado: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: SgcSpacing.sm,
  },
  vacio: {
    paddingVertical: SgcSpacing.sm,
  },
});
