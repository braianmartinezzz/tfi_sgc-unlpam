import { Pressable, StyleSheet, View } from 'react-native';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EstadoBadge } from '@/components/panel/estado-badge';
import { Txt } from '@/components/panel/txt';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatRelativo } from '@/lib/format';
import type { MovimientoPanel } from '@/types/panel';

type MovimientosCardProps = {
  movimientos: MovimientoPanel[];
  onAbrirConvenio: (idInstrumento: number) => void;
};

export function MovimientosCard({ movimientos, onAbrirConvenio }: MovimientosCardProps) {
  const tema = useSgcTheme();

  return (
    <Card titulo="Últimos movimientos">
      {movimientos.length === 0 ? (
        <EstadoVacio texto="Todavía no hay movimientos registrados." />
      ) : (
        <View>
          {movimientos.map((movimiento, indice) => (
            <Pressable
              key={movimiento.id_instrumento}
              accessibilityRole="button"
              onPress={() => onAbrirConvenio(movimiento.id_instrumento)}
              style={({ pressed }) => [
                styles.fila,
                indice % 2 === 1 && { backgroundColor: tema.rowAlternate },
                pressed && { opacity: 0.7 },
              ]}>
              <Txt negrita numberOfLines={2}>
                {movimiento.titulo ?? `Convenio #${movimiento.id_instrumento}`}
              </Txt>
              <View style={styles.estados}>
                {movimiento.estado_anterior ? (
                  <>
                    <EstadoBadge estado={movimiento.estado_anterior} />
                    <Txt variante="detalle" color="textSecondary">
                      →
                    </Txt>
                  </>
                ) : null}
                <EstadoBadge estado={movimiento.estado_nuevo} />
              </View>
              <Txt variante="etiqueta" color="textSecondary">
                {formatRelativo(movimiento.fecha_hora)}
                {movimiento.usuario ? ` · ${movimiento.usuario}` : ''}
              </Txt>
            </Pressable>
          ))}
        </View>
      )}
    </Card>
  );
}

const styles = StyleSheet.create({
  fila: {
    paddingVertical: SgcSpacing.sm,
    paddingHorizontal: SgcSpacing.sm,
    gap: 4,
  },
  estados: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SgcSpacing.sm,
  },
});
