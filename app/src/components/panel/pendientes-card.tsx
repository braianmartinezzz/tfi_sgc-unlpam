import { Pressable, StyleSheet, View } from 'react-native';

import { Card, EstadoVacio } from '@/components/panel/card';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import type { PendienteFirma } from '@/types/panel';

const MAXIMO_VISIBLE = 3;

type PendientesCardProps = {
  pendientes: PendienteFirma[];
  /** Sin conexión se pueden ver, pero no abrir el firmador. */
  sinConexion: boolean;
  onAbrirFirmador: () => void;
  onAbrirConvenio: (idInstrumento: number) => void;
};

function textoDemora(horas: number | null): string {
  if (horas === null) return 'Aprobado, a la espera de firma';
  if (horas < 1) return 'Aprobado hace menos de 1 h';
  if (horas < 48) return `Aprobado hace ${horas} h`;

  return `Aprobado hace ${Math.floor(horas / 24)} días`;
}

export function PendientesCard({
  pendientes,
  sinConexion,
  onAbrirFirmador,
  onAbrirConvenio,
}: PendientesCardProps) {
  const tema = useSgcTheme();
  const visibles = pendientes.slice(0, MAXIMO_VISIBLE);

  return (
    <Card
      titulo="Mis pendientes de firma"
      bordeSuperior={tema.brand}
      acento={
        <Txt variante="subtitulo" style={{ color: tema.brandText }}>
          {pendientes.length}
        </Txt>
      }>
      {visibles.length === 0 ? (
        <EstadoVacio
          texto={
            sinConexion
              ? 'No hay convenios pendientes de firma guardados en este dispositivo.'
              : 'No tenés convenios pendientes de firma.'
          }
        />
      ) : (
        <View style={styles.lista}>
          {visibles.map((pendiente) => (
            <Pressable
              key={pendiente.id_instrumento}
              accessibilityRole="button"
              onPress={() => onAbrirConvenio(pendiente.id_instrumento)}
              style={({ pressed }) => [
                styles.fila,
                { borderColor: tema.border },
                pendiente.demorado && { borderLeftColor: tema.danger, borderLeftWidth: 4 },
                pressed && { backgroundColor: tema.rowAlternate },
              ]}>
              <Txt negrita numberOfLines={2}>
                {pendiente.titulo}
              </Txt>
              <Txt variante="detalle" color="textSecondary" numberOfLines={1}>
                {[pendiente.unidad_academica, pendiente.firmantes.join(', ')]
                  .filter(Boolean)
                  .join(' · ')}
              </Txt>
              <Txt
                variante="etiqueta"
                style={{ color: pendiente.demorado ? tema.danger : tema.textSecondary }}>
                {pendiente.demorado ? 'Demorado · ' : ''}
                {textoDemora(pendiente.horas_pendiente)}
              </Txt>
            </Pressable>
          ))}
        </View>
      )}

      {pendientes.length > MAXIMO_VISIBLE ? (
        <Txt variante="detalle" color="textSecondary">
          y {pendientes.length - MAXIMO_VISIBLE} más en el firmador.
        </Txt>
      ) : null}

      <Pressable
        accessibilityRole="button"
        disabled={sinConexion}
        onPress={onAbrirFirmador}
        style={({ pressed }) => [
          styles.boton,
          { backgroundColor: tema.brand },
          pressed && { opacity: 0.85 },
          sinConexion && { opacity: 0.4 },
        ]}>
        <Txt negrita color="onBrand">
          {sinConexion ? 'Firmar (requiere conexión)' : 'Abrir firmador de convenios'}
        </Txt>
      </Pressable>

    </Card>
  );
}

const styles = StyleSheet.create({
  lista: {
    gap: SgcSpacing.sm,
  },
  fila: {
    borderWidth: 1,
    borderRadius: SgcRadius.sm,
    padding: SgcSpacing.md,
    gap: 2,
  },
  boton: {
    borderRadius: SgcRadius.md,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
  },
});
