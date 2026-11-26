import { StyleSheet, View } from 'react-native';

import { Card } from '@/components/panel/card';
import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import type { ConflictoFirma } from '@/lib/concurrencia-firma';

type Props = {
  conflicto: ConflictoFirma;
  /** Hay una firma dibujada o un comentario en borrador que se podría conservar. */
  hayBorrador: boolean;
  onRecargar: () => void;
  onConservar: () => void;
  onDescartar: () => void;
};

/** Aviso de conflicto de versión: la persona decide qué hacer con lo que ya cargó. */
export function ConflictoFirmaCard({
  conflicto,
  hayBorrador,
  onRecargar,
  onConservar,
  onDescartar,
}: Props) {
  const tema = useSgcTheme();
  // Si el convenio ya no está pendiente no hay versión vigente sobre la cual conservar nada.
  const sePuedeConservar = conflicto.actual !== null && hayBorrador;
  const sePuedeRecargar = conflicto.actual !== null;

  return (
    <Card titulo="Conflicto de versión" bordeSuperior={tema.warning}>
      <View accessibilityRole="alert" style={styles.cuerpo}>
        <Txt color="textSecondary">{conflicto.mensaje}</Txt>
        {conflicto.cambios.map((cambio) => (
          <Txt key={cambio} variante="detalle" color="textSecondary">
            • {cambio}
          </Txt>
        ))}
        <Txt variante="detalle" color="textSecondary">
          Por seguridad no se firmó nada. Revisá la versión vigente antes de volver a confirmar.
        </Txt>
      </View>

      {sePuedeRecargar ? (
        <PressableA11y
          accessibilityRole="button"
          accessibilityHint="Abre la versión actual del convenio y borra la firma y el comentario cargados"
          onPress={onRecargar}
          style={[styles.boton, { backgroundColor: tema.brand }]}>
          <Txt negrita color="onBrand">Recargar versión vigente</Txt>
        </PressableA11y>
      ) : null}

      {sePuedeConservar ? (
        <PressableA11y
          accessibilityRole="button"
          accessibilityHint="Abre la versión actual y mantiene tu firma y comentario para que los revises"
          onPress={onConservar}
          style={[styles.botonSecundario, { borderColor: tema.brand }]}>
          <Txt negrita style={{ color: tema.brandText }}>Conservar mi firma y comentario</Txt>
        </PressableA11y>
      ) : null}

      <PressableA11y
        accessibilityRole="button"
        accessibilityHint="Sale del convenio y borra lo que cargaste"
        onPress={onDescartar}
        style={[styles.botonSecundario, { borderColor: tema.border }]}>
        <Txt negrita>Descartar mis cambios</Txt>
      </PressableA11y>
    </Card>
  );
}

const styles = StyleSheet.create({
  cuerpo: { gap: SgcSpacing.sm },
  boton: { borderRadius: SgcRadius.sm, paddingVertical: SgcSpacing.md, alignItems: 'center' },
  botonSecundario: {
    borderWidth: 1,
    borderRadius: SgcRadius.sm,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
  },
});
