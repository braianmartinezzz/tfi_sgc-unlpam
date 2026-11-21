/** Firma dibujada por el usuario: PNG en base64 (data URI) y cuándo se registró. */
export type FirmaRegistrada = {
  imagen: string;
  fecha: string;
};

/** Verificación biométrica del sistema (Face ID / desbloqueo facial / huella) hecha en este dispositivo. */
export type BiometriaRegistrada = {
  fecha: string;
};

export type CampoBiometrico = 'rostro' | 'huella';

export type IdentidadRegistrada = {
  firma: FirmaRegistrada | null;
  rostro: BiometriaRegistrada | null;
  huella: BiometriaRegistrada | null;
};

export const IDENTIDAD_VACIA: IdentidadRegistrada = { firma: null, rostro: null, huella: null };
