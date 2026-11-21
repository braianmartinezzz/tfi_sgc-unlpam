import { Platform } from 'react-native';

/**
 * Paleta de la aplicación web (web/app/globals.css) llevada a la app móvil:
 * rojo institucional de la UNLPam, barra superior oscura y superficies neutras.
 */
export type SgcPalette = {
  page: string;
  surface: string;
  topbar: string;
  onTopbar: string;
  field: string;
  onField: string;
  fieldPlaceholder: string;
  border: string;
  text: string;
  textSecondary: string;
  brand: string;
  brandText: string;
  onBrand: string;
  rowAlternate: string;
  success: string;
  warning: string;
  info: string;
  danger: string;
};

export const SgcColors: { light: SgcPalette; dark: SgcPalette } = {
  light: {
    page: '#f1f0f5',
    surface: '#ffffff',
    topbar: '#25262c',
    onTopbar: '#ffffff',
    field: '#3a3c45',
    onField: '#ffffff',
    fieldPlaceholder: '#a9acb8',
    border: '#d3d5de',
    text: '#171717',
    textSecondary: '#60646c',
    brand: '#d13b3b',
    brandText: '#c52d34',
    onBrand: '#ffffff',
    rowAlternate: '#eceef4',
    success: '#16a34a',
    warning: '#d97706',
    info: '#2563eb',
    danger: '#d13b3b',
  },
  dark: {
    page: '#17181e',
    surface: '#1c1d22',
    topbar: '#1c1d22',
    onTopbar: '#ededed',
    field: '#2b2d35',
    onField: '#ededed',
    fieldPlaceholder: '#8e919d',
    border: '#454751',
    text: '#ededed',
    textSecondary: '#b0b4ba',
    brand: '#d13b3b',
    brandText: '#ff8a8a',
    onBrand: '#ffffff',
    rowAlternate: '#24262e',
    success: '#4ade80',
    warning: '#fbbf24',
    info: '#60a5fa',
    danger: '#ff8a8a',
  },
};

type EstadoColor = { bg: string; fg: string };

/** Mismos colores que EstadoBadge de la web (Tailwind), en modo claro y oscuro. */
export const EstadoColors: Record<string, { light: EstadoColor; dark: EstadoColor }> = {
  Borrador: {
    light: { bg: '#e2e8f0', fg: '#0f172a' },
    dark: { bg: '#334155', fg: '#ffffff' },
  },
  EnCorreccion: {
    light: { bg: '#fef3c7', fg: '#451a03' },
    dark: { bg: '#78350f', fg: '#fef3c7' },
  },
  'Revisión': {
    light: { bg: '#dbeafe', fg: '#172554' },
    dark: { bg: '#1e3a8a', fg: '#dbeafe' },
  },
  Aprobado: {
    light: { bg: '#d1fae5', fg: '#022c22' },
    dark: { bg: '#064e3b', fg: '#d1fae5' },
  },
  Firmado: {
    light: { bg: '#dcfce7', fg: '#052e16' },
    dark: { bg: '#14532d', fg: '#dcfce7' },
  },
  Rescindido: {
    light: { bg: '#ffe4e6', fg: '#4c0519' },
    dark: { bg: '#881337', fg: '#ffe4e6' },
  },
  Rechazado: {
    light: { bg: '#fee2e2', fg: '#450a0a' },
    dark: { bg: '#7f1d1d', fg: '#fee2e2' },
  },
  Archivado: {
    light: { bg: '#ede9fe', fg: '#2e1065' },
    dark: { bg: '#4c1d95', fg: '#ede9fe' },
  },
};

export const EstadoNeutro = {
  light: { bg: '#e5e5e5', fg: '#171717' },
  dark: { bg: '#404040', fg: '#ffffff' },
};

/** La web usa Roboto Condensed; en la app se usa la variante condensada del sistema cuando existe. */
export const SgcFont = Platform.select<string | undefined>({
  android: 'sans-serif-condensed',
  web: '"Roboto Condensed", Arial, Helvetica, sans-serif',
  default: undefined,
});

/** Multiplicador del tamaño de letra (chica 0.9, mediana 1, grande 1.15). Pensado para "Mi perfil". */
export const TextScale = {
  small: 0.9,
  medium: 1,
  large: 1.15,
} as const;

export const SgcSpacing = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24 } as const;
export const SgcRadius = { sm: 6, md: 10, pill: 999 } as const;
