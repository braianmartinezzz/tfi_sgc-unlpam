import { useEffect, useState } from 'react';

import { EstadoColors, EstadoNeutro, SgcColors, TextScale, type SgcPalette } from '@/constants/sgc-theme';
import {
  getThemePreference,
  getTextSizePreference,
  setTextSizePreference,
  setThemePreference,
  type TextSizePreference,
  type ThemePreference,
} from '@/lib/theme-storage';

export type SgcTheme = SgcPalette & {
  scheme: 'light' | 'dark';
  preference: ThemePreference;
  textSize: TextSizePreference;
  fontScale: number;
  estadoColor: (estado: string) => { bg: string; fg: string };
  setPreference: (preference: ThemePreference) => void;
  setTextSize: (textSize: TextSizePreference) => void;
};

let sharedPreference: ThemePreference = 'light';
let sharedTextSize: TextSizePreference = 'medium';
let initialized = false;
const themeListeners = new Set<(preference: ThemePreference) => void>();
const textSizeListeners = new Set<(textSize: TextSizePreference) => void>();

function notifyTheme(preference: ThemePreference) {
  sharedPreference = preference;
  themeListeners.forEach((listener) => listener(preference));
}

function notifyTextSize(textSize: TextSizePreference) {
  sharedTextSize = textSize;
  textSizeListeners.forEach((listener) => listener(textSize));
}

export function useSgcTheme(): SgcTheme {
  const [preference, setPreferenceState] = useState<ThemePreference>(sharedPreference);
  const [textSize, setTextSizeState] = useState<TextSizePreference>(sharedTextSize);

  useEffect(() => {
    themeListeners.add(setPreferenceState);
    textSizeListeners.add(setTextSizeState);

    if (!initialized) {
      initialized = true;
      void getThemePreference().then((saved) => {
        if (saved) notifyTheme(saved);
      });
      void getTextSizePreference().then((saved) => {
        if (saved) notifyTextSize(saved);
      });
    }

    return () => {
      themeListeners.delete(setPreferenceState);
      textSizeListeners.delete(setTextSizeState);
    };
  }, []);

  const scheme = preference;

  const setPreference = (nextPreference: ThemePreference) => {
    notifyTheme(nextPreference);
    void setThemePreference(nextPreference);
  };

  const setTextSize = (nextTextSize: TextSizePreference) => {
    notifyTextSize(nextTextSize);
    void setTextSizePreference(nextTextSize);
  };

  return {
    ...SgcColors[scheme],
    scheme,
    preference,
    textSize,
    fontScale: TextScale[textSize],
    estadoColor: (estado: string) => (EstadoColors[estado] ?? EstadoNeutro)[scheme],
    setPreference,
    setTextSize,
  };
}
