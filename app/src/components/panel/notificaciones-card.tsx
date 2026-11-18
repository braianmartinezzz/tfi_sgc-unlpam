import { Pressable, StyleSheet, View } from 'react-native';

import { Card, Enlace, EstadoVacio } from '@/components/panel/card';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatRelativo } from '@/lib/format';
import type { AlertaPanel, NotificacionPanel } from '@/types/panel';

const MAXIMO_NOTIFICACIONES = 4;

const ETIQUETA_ALERTA: Record<AlertaPanel['tipo'], string> = {
  PREAVISO: 'Preaviso de vencimiento',
  FIRMA_DEMORADA: 'Firma demorada',
};

type NotificacionesCardProps = {
  alertas: AlertaPanel[];
  notificaciones: NotificacionPanel[];
  noLeidas: number;
  onAbrirConvenio: (idInstrumento: number) => void;
  onVerTodas: () => void;
};

export function NotificacionesCard({
  alertas,
  notificaciones,
  noLeidas,
  onAbrirConvenio,
  onVerTodas,
}: NotificacionesCardProps) {
  const tema = useSgcTheme();
  const visibles = notificaciones.slice(0, MAXIMO_NOTIFICACIONES);
  const sinNovedades = alertas.length === 0 && visibles.length === 0;

  return (
    <Card
      titulo="Avisos y notificaciones"
      acento={
        noLeidas > 0 ? (
          <View style={[styles.contador, { backgroundColor: tema.brand }]}>
            <Txt variante="etiqueta" color="onBrand">
              {noLeidas} sin leer
            </Txt>
          </View>
        ) : null
      }>
      {sinNovedades ? <EstadoVacio texto="Sin novedades por el momento." /> : null}

      {alertas.map((alerta) => (
        <Pressable
          key={`${alerta.tipo}-${alerta.id_instrumento}`}
          accessibilityRole="button"
          onPress={() => onAbrirConvenio(alerta.id_instrumento)}
          style={({ pressed }) => [
            styles.fila,
            {
              borderColor: tema.border,
              borderLeftColor: alerta.tipo === 'FIRMA_DEMORADA' ? tema.danger : tema.warning,
            },
            pressed && { backgroundColor: tema.rowAlternate },
          ]}>
          <Txt variante="etiqueta" color="textSecondary">
            {ETIQUETA_ALERTA[alerta.tipo]}
          </Txt>
          <Txt negrita numberOfLines={2}>
            {alerta.titulo}
          </Txt>
          <Txt variante="detalle" color="textSecondary">
            {alerta.mensaje}
          </Txt>
        </Pressable>
      ))}

      {visibles.map((notificacion) => (
        <Pressable
          key={notificacion.id_notificacion}
          accessibilityRole="button"
          disabled={notificacion.id_instrumento === null}
          onPress={() =>
            notificacion.id_instrumento !== null && onAbrirConvenio(notificacion.id_instrumento)
          }
          style={({ pressed }) => [
            styles.fila,
            {
              borderColor: tema.border,
              borderLeftColor: notificacion.leida ? tema.border : tema.info,
            },
            pressed && { backgroundColor: tema.rowAlternate },
          ]}>
          <Txt negrita={!notificacion.leida} numberOfLines={3}>
            {notificacion.mensaje}
          </Txt>
          <Txt variante="etiqueta" color="textSecondary">
            {formatRelativo(notificacion.fecha_generacion)}
          </Txt>
        </Pressable>
      ))}

      {notificaciones.length > MAXIMO_NOTIFICACIONES ? (
        <Enlace texto="Ver todas las notificaciones" onPress={onVerTodas} />
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  contador: {
    borderRadius: SgcRadius.pill,
    paddingHorizontal: 10,
    paddingVertical: 3,
  },
  fila: {
    borderWidth: 1,
    borderLeftWidth: 4,
    borderRadius: SgcRadius.sm,
    padding: SgcSpacing.md,
    gap: 2,
  },
});
