import { useEffect, useState } from 'react';

import { EstadoColors, EstadoNeutro, SgcColors, TextScale, type SgcPalette } from '@/constants/sgc-theme';
import { getThemePreference, setThemePreference, type ThemePreference } from '@/lib/theme-storage';

export type SgcTheme = SgcPalette & {
  scheme: 'light' | 'dark';
  preference: ThemePreference;
  fontScale: number;
  estadoColor: (estado: string) => { bg: string; fg: string };
  setPreference: (preference: ThemePreference) => void;
};

let sharedPreference: ThemePreference = 'light';
let initialized = false;
const listeners = new Set<(preference: ThemePreference) => void>();

function notify(preference: ThemePreference) {
  sharedPreference = preference;
  listeners.forEach((listener) => listener(preference));
}

export function useSgcTheme(): SgcTheme {
  const [preference, setPreferenceState] = useState<ThemePreference>(sharedPreference);

  useEffect(() => {
    listeners.add(setPreferenceState);

    if (!initialized) {
      initialized = true;
      void getThemePreference().then((saved) => {
        if (saved) notify(saved);
      });
    }

    return () => {
      listeners.delete(setPreferenceState);
    };
  }, []);

  const scheme = preference;

  const setPreference = (nextPreference: ThemePreference) => {
    notify(nextPreference);
    void setThemePreference(nextPreference);
  };

  return {
    ...SgcColors[scheme],
    scheme,
    preference,
    // Cuando exista la preferencia "tamaño de letra" de Mi perfil, se conecta acá.
    fontScale: TextScale.medium,
    estadoColor: (estado: string) => (EstadoColors[estado] ?? EstadoNeutro)[scheme],
    setPreference,
  };
}
