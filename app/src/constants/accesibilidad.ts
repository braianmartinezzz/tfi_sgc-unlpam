/**
 * Criterios de accesibilidad de la app (Fase 7). Se concentran acá para que las pantallas
 * y los tests usen los mismos números.
 */

/** Lado mínimo de un blanco táctil: 44 (Apple HIG); Material pide 48 dp, con el espaciado entre botones se compensa. */
export const AREA_TACTIL_MINIMA = 44;

/** Ningún texto baja de este tamaño, ni siquiera con "Chica" en Mi perfil. */
export const FUENTE_MINIMA = 12;

/** Tope del agrandado que hace el sistema (Ajustes > Accesibilidad > Tamaño de fuente), para no romper el diseño. */
export const ESCALA_SISTEMA_MAXIMA = 1.6;

/** WCAG 2.1 AA: texto normal. */
export const CONTRASTE_MINIMO_TEXTO = 4.5;

/** WCAG 2.1 AA: bordes y componentes de interfaz. */
export const CONTRASTE_MINIMO_COMPONENTE = 3;
