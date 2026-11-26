import { StyleSheet, View } from 'react-native';

import { Card } from '@/components/panel/card';
import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

type Acceso = {
  clave: string;
  titulo: string;
  detalle: string;
  /** Requiere conexión: se deshabilita en modo offline. */
  requiereConexion: boolean;
  /** Sin handler el acceso se muestra deshabilitado como "Próximamente". */
  onPress?: () => void;
};

type AccesosRapidosProps = {
  sinConexion: boolean;
  puedeFirmar: boolean;
  /** Los opcionales sin implementar se pasan como `undefined`. */
  onEscanearQr?: () => void;
  onFirmador: () => void;
  onBusquedaAvanzada: () => void;
  onAyuda?: () => void;
};

export function AccesosRapidos({
  sinConexion,
  puedeFirmar,
  onEscanearQr,
  onFirmador,
  onBusquedaAvanzada,
  onAyuda,
}: AccesosRapidosProps) {
  const tema = useSgcTheme();

  const accesos: Acceso[] = [
    {
      clave: 'qr',
      titulo: 'Escanear QR',
      detalle: 'Abre la ficha del convenio',
      requiereConexion: true,
      onPress: onEscanearQr,
    },
    ...(puedeFirmar
      ? [
          {
            clave: 'firmador',
            titulo: 'Firmador',
            detalle: 'Convenios por firmar',
            requiereConexion: false,
            onPress: onFirmador,
          },
        ]
      : []),
    {
      clave: 'busqueda',
      titulo: 'Búsqueda avanzada',
      detalle: 'Con filtros',
      requiereConexion: true,
      onPress: onBusquedaAvanzada,
    },
    {
      clave: 'ayuda',
      titulo: 'Ayuda',
      detalle: 'Manual de usuario',
      requiereConexion: false,
      onPress: onAyuda,
    },
  ];

  return (
    <Card titulo="Accesos rápidos">
      <View style={styles.grilla}>
        {accesos.map((acceso) => {
          const proximamente = !acceso.onPress;
          const sinRed = sinConexion && acceso.requiereConexion;
          const deshabilitado = proximamente || sinRed;

          return (
            <PressableA11y
              key={acceso.clave}
              accessibilityRole="button"
              accessibilityLabel={`${acceso.titulo}. ${proximamente ? 'Próximamente' : acceso.detalle}`}
              accessibilityHint={sinRed ? 'No disponible sin conexión' : undefined}
              accessibilityState={{ disabled: deshabilitado }}
              disabled={deshabilitado}
              onPress={acceso.onPress}
              style={({ pressed }) => [
                styles.acceso,
                { borderColor: tema.border, backgroundColor: tema.rowAlternate },
                pressed && { opacity: 0.7 },
                deshabilitado && { opacity: 0.4 },
              ]}>
              <Txt negrita>{acceso.titulo}</Txt>
              <Txt variante="detalle" color="textSecondary">
                {proximamente ? 'Próximamente' : acceso.detalle}
              </Txt>
            </PressableA11y>
          );
        })}
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  grilla: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: SgcSpacing.sm,
  },
  acceso: {
    flexGrow: 1,
    flexBasis: '45%',
    borderWidth: 1,
    borderRadius: SgcRadius.sm,
    padding: SgcSpacing.md,
    gap: 2,
  },
});
