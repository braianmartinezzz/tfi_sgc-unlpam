import { CONTRASTE_MINIMO_TEXTO } from '@/constants/accesibilidad';
import { EstadoColors, EstadoNeutro, SgcColors, type SgcPalette } from '@/constants/sgc-theme';
import {
  diasHastaVencimiento,
  razonDeContraste,
  textoVencimiento,
} from '@/lib/accesibilidad';

type Par = [texto: keyof SgcPalette, fondo: keyof SgcPalette];

/** Combinaciones de texto sobre fondo que la app usa de verdad. */
const PARES_DE_TEXTO: Par[] = [
  ['text', 'surface'],
  ['text', 'page'],
  ['text', 'rowAlternate'],
  ['textSecondary', 'surface'],
  ['textSecondary', 'page'],
  ['textSecondary', 'rowAlternate'],
  ['brandText', 'surface'],
  ['brandText', 'page'],
  ['brandText', 'rowAlternate'],
  ['danger', 'surface'],
  ['danger', 'page'],
  ['danger', 'rowAlternate'],
  ['onBrand', 'brand'],
  ['onTopbar', 'topbar'],
  ['onField', 'field'],
  ['fieldPlaceholder', 'field'],
];

describe('contraste de la paleta (WCAG AA)', () => {
  for (const esquema of ['light', 'dark'] as const) {
    for (const [texto, fondo] of PARES_DE_TEXTO) {
      it(`${esquema}: ${texto} sobre ${fondo} llega a ${CONTRASTE_MINIMO_TEXTO}:1`, () => {
        const paleta = SgcColors[esquema];
        expect(razonDeContraste(paleta[texto], paleta[fondo])).toBeGreaterThanOrEqual(
          CONTRASTE_MINIMO_TEXTO,
        );
      });
    }
  }

  it('los colores de estado (modo claro y oscuro) llegan a 4.5:1', () => {
    const todos = [...Object.values(EstadoColors), EstadoNeutro];

    for (const colores of todos) {
      for (const esquema of ['light', 'dark'] as const) {
        const { fg, bg } = colores[esquema];
        expect(razonDeContraste(fg, bg)).toBeGreaterThanOrEqual(CONTRASTE_MINIMO_TEXTO);
      }
    }
  });

  it('calcula los extremos de la escala', () => {
    expect(razonDeContraste('#000000', '#ffffff')).toBeCloseTo(21, 0);
    expect(razonDeContraste('#ffffff', '#ffffff')).toBeCloseTo(1, 5);
  });
});

describe('texto de vencimiento', () => {
  const hoy = new Date(2026, 10, 25); // 25/11/2026

  it('cuenta días calendario hacia adelante y hacia atrás', () => {
    expect(diasHastaVencimiento('2026-12-05', hoy)).toBe(10);
    expect(diasHastaVencimiento('2026-11-25', hoy)).toBe(0);
    expect(diasHastaVencimiento('2026-11-20', hoy)).toBe(-5);
  });

  it('ignora la hora que venga con la fecha', () => {
    expect(diasHastaVencimiento('2026-11-26T00:00:00.000000Z', hoy)).toBe(1);
  });

  it('devuelve null si la fecha no se puede leer', () => {
    expect(diasHastaVencimiento('sin fecha', hoy)).toBeNull();
  });

  it('redacta la frase según el caso', () => {
    expect(textoVencimiento(10)).toBe('Vence en 10 días');
    expect(textoVencimiento(1)).toBe('Vence mañana');
    expect(textoVencimiento(0)).toBe('Vence hoy');
    expect(textoVencimiento(-1)).toBe('Venció ayer');
    expect(textoVencimiento(-5)).toBe('Venció hace 5 días');
  });
});
