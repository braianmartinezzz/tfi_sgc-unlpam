import { StyleSheet, Text, type TextProps } from 'react-native';

import { SgcFont } from '@/constants/sgc-theme';
import { useSgcTheme, type SgcTheme } from '@/hooks/use-sgc-theme';

type Variante = 'metrica' | 'titulo' | 'subtitulo' | 'cuerpo' | 'detalle' | 'etiqueta';

type ColorTexto = 'text' | 'textSecondary' | 'brandText' | 'onTopbar' | 'onBrand';

export type TxtProps = TextProps & {
  variante?: Variante;
  color?: ColorTexto;
  negrita?: boolean;
};

const TAMANIOS: Record<Variante, { size: number; lineHeight: number; weight: '400' | '500' | '700' }> = {
  metrica: { size: 34, lineHeight: 38, weight: '700' },
  titulo: { size: 24, lineHeight: 30, weight: '700' },
  subtitulo: { size: 17, lineHeight: 22, weight: '700' },
  cuerpo: { size: 15, lineHeight: 21, weight: '500' },
  detalle: { size: 13, lineHeight: 18, weight: '400' },
  etiqueta: { size: 12, lineHeight: 16, weight: '700' },
};

export function Txt({ variante = 'cuerpo', color = 'text', negrita, style, ...resto }: TxtProps) {
  const tema = useSgcTheme();
  const base = TAMANIOS[variante];

  return (
    <Text
      style={[
        styles.base,
        {
          color: colorDe(tema, color),
          fontSize: base.size * tema.fontScale,
          lineHeight: base.lineHeight * tema.fontScale,
          fontWeight: negrita ? '700' : base.weight,
        },
        style,
      ]}
      {...resto}
    />
  );
}

function colorDe(tema: SgcTheme, color: ColorTexto): string {
  return tema[color];
}

const styles = StyleSheet.create({
  base: {
    fontFamily: SgcFont,
  },
});
