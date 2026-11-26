import { StyleSheet, Text, type TextProps } from 'react-native';

import { ESCALA_SISTEMA_MAXIMA, FUENTE_MINIMA } from '@/constants/accesibilidad';
import { SgcFont } from '@/constants/sgc-theme';
import { useSgcTheme, type SgcTheme } from '@/hooks/use-sgc-theme';

type Variante = 'metrica' | 'titulo' | 'subtitulo' | 'cuerpo' | 'detalle' | 'etiqueta';

type ColorTexto = 'text' | 'textSecondary' | 'brandText' | 'onTopbar' | 'onBrand';

export type TxtProps = TextProps & {
  variante?: Variante;
  color?: ColorTexto;
  negrita?: boolean;
  /** Lo anuncia el lector de pantalla como encabezado (permite saltar entre secciones). `titulo` ya lo es. */
  encabezado?: boolean;
};

const TAMANIOS: Record<Variante, { size: number; lineHeight: number; weight: '400' | '500' | '700' }> = {
  metrica: { size: 34, lineHeight: 38, weight: '700' },
  titulo: { size: 24, lineHeight: 30, weight: '700' },
  subtitulo: { size: 17, lineHeight: 22, weight: '700' },
  cuerpo: { size: 15, lineHeight: 21, weight: '500' },
  detalle: { size: 13, lineHeight: 18, weight: '400' },
  etiqueta: { size: 12, lineHeight: 16, weight: '700' },
};

export function Txt({
  variante = 'cuerpo',
  color = 'text',
  negrita,
  encabezado,
  style,
  ...resto
}: TxtProps) {
  const tema = useSgcTheme();
  const base = TAMANIOS[variante];
  const plano = StyleSheet.flatten(style) ?? {};

  // El tamaño elegido en "Mi perfil" también alcanza a los textos que fijan su propio fontSize.
  const fontSize = Math.max(
    (typeof plano.fontSize === 'number' ? plano.fontSize : base.size) * tema.fontScale,
    FUENTE_MINIMA,
  );
  const lineaBase = typeof plano.lineHeight === 'number' ? plano.lineHeight : base.lineHeight;
  const lineHeight = Math.max(lineaBase * tema.fontScale, Math.ceil(fontSize * 1.2));

  return (
    <Text
      accessibilityRole={encabezado || variante === 'titulo' ? 'header' : undefined}
      maxFontSizeMultiplier={ESCALA_SISTEMA_MAXIMA}
      {...resto}
      style={[
        styles.base,
        {
          color: colorDe(tema, color),
          fontWeight: negrita ? '700' : base.weight,
        },
        style,
        { fontSize, lineHeight },
      ]}
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
