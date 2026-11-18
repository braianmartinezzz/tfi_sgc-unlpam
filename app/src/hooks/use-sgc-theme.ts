import { useColorScheme } from 'react-native';

import { EstadoColors, EstadoNeutro, SgcColors, TextScale, type SgcPalette } from '@/constants/sgc-theme';

export type SgcTheme = SgcPalette & {
  scheme: 'light' | 'dark';
  fontScale: number;
  estadoColor: (estado: string) => { bg: string; fg: string };
};

export function useSgcTheme(): SgcTheme {
  const scheme = useColorScheme() === 'dark' ? 'dark' : 'light';

  return {
    ...SgcColors[scheme],
    scheme,
    // Cuando exista la preferencia "tamaño de letra" de Mi perfil, se conecta acá.
    fontScale: TextScale.medium,
    estadoColor: (estado: string) => (EstadoColors[estado] ?? EstadoNeutro)[scheme],
  };
}
