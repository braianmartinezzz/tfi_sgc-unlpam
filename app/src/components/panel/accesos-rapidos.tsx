import { Pressable, StyleSheet, View } from 'react-native';

import { Card } from '@/components/panel/card';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

type Acceso = {
  clave: string;
  titulo: string;
  detalle: string;
  /** Requiere conexión: se deshabilita en modo offline. */
  requiereConexion: boolean;
  onPress: () => void;
};

type AccesosRapidosProps = {
  sinConexion: boolean;
  puedeFirmar: boolean;
  onEscanearQr: () => void;
  onFirmador: () => void;
  onBusquedaAvanzada: () => void;
  onAyuda: () => void;
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
          const deshabilitado = sinConexion && acceso.requiereConexion;

          return (
            <Pressable
              key={acceso.clave}
              accessibilityRole="button"
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
                {acceso.detalle}
              </Txt>
            </Pressable>
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
