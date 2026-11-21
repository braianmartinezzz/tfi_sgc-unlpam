import { StyleSheet, View } from 'react-native';

import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

type StatBoxProps = {
  valor: number | string;
  etiqueta: string;
  /** Color del borde superior (identifica la métrica). */
  color: string;
  /** Aclaración corta debajo de la etiqueta. */
  nota?: string;
};

export function StatBox({ valor, etiqueta, color, nota }: StatBoxProps) {
  const tema = useSgcTheme();

  return (
    <View
      accessible
      accessibilityLabel={`${etiqueta}: ${valor}`}
      style={[
        styles.caja,
        { backgroundColor: tema.surface, borderColor: tema.border, borderTopColor: color },
      ]}>
      <Txt variante="metrica">{valor}</Txt>
      <Txt variante="detalle" color="textSecondary">
        {etiqueta}
      </Txt>
      {nota ? (
        <Txt variante="etiqueta" color="textSecondary">
          {nota}
        </Txt>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  caja: {
    flexGrow: 1,
    flexBasis: '45%',
    borderWidth: 1,
    borderTopWidth: 3,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.lg,
    paddingVertical: SgcSpacing.md,
    gap: 2,
  },
});
