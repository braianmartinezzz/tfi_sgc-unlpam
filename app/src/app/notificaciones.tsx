import { useRouter } from 'expo-router';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { OfflineBanner } from '@/components/panel/offline-banner';
import { Txt } from '@/components/panel/txt';
import { Destinos } from '@/constants/navigation';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { usePanel } from '@/hooks/use-panel';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatRelativo } from '@/lib/format';

/**
 * Lista completa de las notificaciones que trae el panel. Reutiliza `usePanel`, que arranca
 * con la última respuesta en memoria, así que normalmente abre al instante.
 */
export default function NotificacionesScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const { panel, estado, sinConexion, actualizado, actualizando, error, actualizar } = usePanel();

  const notificaciones = panel?.notificaciones.items ?? [];
  const noLeidas = panel?.notificaciones.no_leidas ?? 0;

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Notificaciones" />

      {sinConexion ? <OfflineBanner actualizado={actualizado} /> : null}

      <ScrollView
        contentContainerStyle={styles.contenido}
        refreshControl={
          <RefreshControl
            refreshing={actualizando}
            onRefresh={actualizar}
            tintColor={tema.brand}
            colors={[tema.brand]}
          />
        }>
        <View style={styles.columna}>
          {estado === 'cargando' ? (
            <View style={styles.centrado}>
              <ActivityIndicator color={tema.brand} />
              <Txt variante="detalle" color="textSecondary">
                Cargando notificaciones…
              </Txt>
            </View>
          ) : null}

          {estado === 'error' ? (
            <Card titulo="No se pudieron cargar las notificaciones" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">{error}</Txt>
              <Txt variante="detalle" color="textSecondary">
                Deslizá hacia abajo para reintentar.
              </Txt>
            </Card>
          ) : null}

          {estado === 'listo' && sinConexion ? (
            <Card>
              <EstadoVacio texto="Las notificaciones no están disponibles sin conexión." />
            </Card>
          ) : null}

          {estado === 'listo' && !sinConexion ? (
            <>
              <Txt variante="detalle" color="textSecondary">
                {noLeidas > 0
                  ? `${noLeidas} sin leer · ${notificaciones.length} en total`
                  : `${notificaciones.length} en total`}
              </Txt>

              {notificaciones.length === 0 ? (
                <Card>
                  <EstadoVacio texto="No tenés notificaciones." />
                </Card>
              ) : null}

              {notificaciones.map((notificacion) => {
                const idInstrumento = notificacion.id_instrumento;

                return (
                  <Pressable
                    key={notificacion.id_notificacion}
                    accessibilityRole={idInstrumento === null ? 'text' : 'button'}
                    accessibilityHint={idInstrumento === null ? undefined : 'Abre el convenio'}
                    disabled={idInstrumento === null}
                    onPress={() => idInstrumento !== null && router.push(Destinos.convenio(idInstrumento))}
                    style={({ pressed }) => [
                      styles.fila,
                      {
                        backgroundColor: tema.surface,
                        borderColor: tema.border,
                        borderLeftColor: notificacion.leida ? tema.border : tema.info,
                      },
                      pressed && { backgroundColor: tema.rowAlternate },
                    ]}>
                    <Txt negrita={!notificacion.leida}>{notificacion.mensaje}</Txt>
                    <Txt variante="etiqueta" color="textSecondary">
                      {formatRelativo(notificacion.fecha_generacion)}
                      {idInstrumento !== null ? ` · Convenio #${idInstrumento}` : ''}
                    </Txt>
                  </Pressable>
                );
              })}
            </>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  contenido: { padding: SgcSpacing.lg, paddingBottom: SgcSpacing.xl * 2 },
  columna: { width: '100%', maxWidth: 800, alignSelf: 'center', gap: SgcSpacing.md },
  centrado: { alignItems: 'center', gap: SgcSpacing.sm, padding: SgcSpacing.xl },
  fila: {
    borderWidth: 1,
    borderLeftWidth: 4,
    borderRadius: SgcRadius.sm,
    padding: SgcSpacing.md,
    gap: 2,
  },
});
