import { AccessibilityInfo } from 'react-native';

/** Lee un mensaje en voz alta con TalkBack / VoiceOver (no hace nada si no hay lector activo). */
export function anunciar(mensaje: string | null | undefined): void {
  if (!mensaje) return;

  try {
    AccessibilityInfo.announceForAccessibility(mensaje);
  } catch {
    // Plataformas sin soporte (por ejemplo la versión web): el aviso visual alcanza.
  }
}

/** Luminancia relativa de un color #rrggbb, según WCAG 2.1. */
export function luminanciaRelativa(hex: string): number {
  const limpio = hex.replace('#', '');
  const numero = parseInt(limpio.length === 3 ? limpio.replace(/./g, '$&$&') : limpio, 16);
  const canales = [(numero >> 16) & 255, (numero >> 8) & 255, numero & 255].map((valor) => {
    const normal = valor / 255;
    return normal <= 0.03928 ? normal / 12.92 : Math.pow((normal + 0.055) / 1.055, 2.4);
  });

  return 0.2126 * canales[0] + 0.7152 * canales[1] + 0.0722 * canales[2];
}

/** Razón de contraste entre dos colores (de 1 a 21). */
export function razonDeContraste(a: string, b: string): number {
  const [mayor, menor] = [luminanciaRelativa(a), luminanciaRelativa(b)].sort((x, y) => y - x);
  return (mayor + 0.05) / (menor + 0.05);
}

/**
 * Días que faltan para una fecha de vencimiento (negativo si ya pasó). Compara solo el día
 * calendario, sin horas, para que no dependa de la zona horaria. `null` si la fecha no es válida.
 */
export function diasHastaVencimiento(fecha: string, ahora: Date = new Date()): number | null {
  const partes = /^(\d{4})-(\d{2})-(\d{2})/.exec(fecha);
  if (!partes) return null;

  const vencimiento = Date.UTC(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3]));
  const hoy = Date.UTC(ahora.getFullYear(), ahora.getMonth(), ahora.getDate());

  return Math.round((vencimiento - hoy) / 86_400_000);
}

/** Frase corta del vencimiento, pensada para leerse sin depender del color. */
export function textoVencimiento(dias: number): string {
  if (dias === 0) return 'Vence hoy';
  if (dias === 1) return 'Vence mañana';
  if (dias > 1) return `Vence en ${dias} días`;
  if (dias === -1) return 'Venció ayer';

  return `Venció hace ${-dias} días`;
}
