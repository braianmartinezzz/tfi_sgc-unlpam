import { StyleSheet, View } from 'react-native';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EstadoBadge } from '@/components/panel/estado-badge';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import type { UnidadPanel } from '@/types/panel';

const ORDEN_ESTADOS = [
  'Borrador',
  'EnCorreccion',
  'Revisión',
  'Aprobado',
  'Firmado',
  'Rescindido',
  'Rechazado',
  'Archivado',
];

/** Cantidad de convenios en cada estado (solo los que el usuario puede ver). */
export function PorEstadoCard({ porEstado }: { porEstado: Record<string, number> }) {
  const estados = ORDEN_ESTADOS.filter((estado) => (porEstado[estado] ?? 0) > 0);

  return (
    <Card titulo="Convenios por estado">
      {estados.length === 0 ? (
        <EstadoVacio texto="No hay convenios para mostrar." />
      ) : (
        <View style={styles.estados}>
          {estados.map((estado) => (
            <View key={estado} style={styles.estado}>
              <EstadoBadge estado={estado} />
              <Txt variante="subtitulo">{porEstado[estado]}</Txt>
            </View>
          ))}
        </View>
      )}
    </Card>
  );
}

const MAXIMO_UNIDADES = 5;

/** Barras horizontales con los convenios de cada unidad académica. */
export function PorUnidadCard({ unidades }: { unidades: UnidadPanel[] }) {
  const tema = useSgcTheme();
  const visibles = unidades.slice(0, MAXIMO_UNIDADES);
  const maximo = Math.max(1, ...visibles.map((unidad) => unidad.total));

  return (
    <Card titulo="Convenios por unidad">
      {visibles.length === 0 ? (
        <EstadoVacio texto="No hay convenios para mostrar." />
      ) : (
        <View style={styles.barras}>
          {visibles.map((unidad) => (
            <View key={unidad.id_unidad_academica} style={styles.barra}>
              <Txt variante="detalle" color="textSecondary" style={styles.sigla} numberOfLines={1}>
                {unidad.sigla ?? unidad.nombre ?? 'Sin unidad'}
              </Txt>
              <View style={[styles.pista, { backgroundColor: tema.rowAlternate }]}>
                <View
                  style={[
                    styles.relleno,
                    { backgroundColor: tema.brand, width: `${(unidad.total / maximo) * 100}%` },
                  ]}
                />
              </View>
              <Txt negrita style={styles.total}>
                {unidad.total}
              </Txt>
            </View>
          ))}
        </View>
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  estados: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: SgcSpacing.md,
  },
  estado: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SgcSpacing.sm,
    minWidth: '44%',
  },
  barras: {
    gap: SgcSpacing.sm,
  },
  barra: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SgcSpacing.sm,
  },
  sigla: {
    width: 84,
  },
  pista: {
    flex: 1,
    height: 10,
    borderRadius: SgcRadius.pill,
    overflow: 'hidden',
  },
  relleno: {
    height: '100%',
    borderRadius: SgcRadius.pill,
  },
  total: {
    width: 28,
    textAlign: 'right',
  },
});
