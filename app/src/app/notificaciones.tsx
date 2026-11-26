import { useRouter } from 'expo-router';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { useState } from 'react';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { OfflineBanner } from '@/components/panel/offline-banner';
import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { AREA_TACTIL_MINIMA } from '@/constants/accesibilidad';
import { Destinos } from '@/constants/navigation';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { usePanel } from '@/hooks/use-panel';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { anunciar } from '@/lib/accesibilidad';
import { formatRelativo } from '@/lib/format';
import { marcarNotificacionLeida, marcarTodasNotificacionesLeidas } from '@/services/notificaciones';

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

  const [marcando, setMarcando] = useState(false);

  async function abrirNotificacion(notificacionId: number, idInstrumento: number | null) {
    if (!notificacionId) return;

    try {
      await marcarNotificacionLeida(notificacionId);
    } catch {
      // Si no se pudo marcar (ej. 404) igual se abre el convenio; el panel se refresca abajo.
    } finally {
      if (idInstrumento !== null) router.push(Destinos.convenio(idInstrumento));
      void actualizar();
    }
  }

  async function marcarTodas() {
    if (marcando) return;
    setMarcando(true);
    try {
      await marcarTodasNotificacionesLeidas();
      await actualizar();
      anunciar('Todas las notificaciones quedaron como leídas.');
    } finally {
      setMarcando(false);
    }
  }

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
            <View style={styles.centrado} accessibilityLiveRegion="polite">
              <ActivityIndicator color={tema.brand} />
              <Txt variante="detalle" color="textSecondary">
                Cargando notificaciones…
              </Txt>
            </View>
          ) : null}

          {estado === 'error' ? (
            <Card titulo="No se pudieron cargar las notificaciones" bordeSuperior={tema.danger}>
              <Txt color="textSecondary" accessibilityRole="alert">
                {error}
              </Txt>
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
              <Txt variante="detalle" color="textSecondary" accessibilityLiveRegion="polite">
                {noLeidas > 0
                  ? `${noLeidas} sin leer · ${notificaciones.length} en total`
                  : `${notificaciones.length} en total`}
              </Txt>

              {noLeidas > 0 ? (
                <PressableA11y
                  accessibilityRole="button"
                  accessibilityHint="Marca todas las notificaciones como leídas"
                  accessibilityState={{ disabled: marcando, busy: marcando }}
                  disabled={marcando}
                  onPress={() => void marcarTodas()}
                  style={({ pressed }) => [styles.marcarTodas, pressed && { opacity: 0.7 }]}>
                  <Txt variante="detalle" negrita style={{ color: tema.brandText }}>
                    {marcando ? 'Marcando...' : 'Marcar todas como leídas'}
                  </Txt>
                </PressableA11y>
              ) : null}

              {notificaciones.length === 0 ? (
                <Card>
                  <EstadoVacio texto="No tenés notificaciones." />
                </Card>
              ) : null}

              {notificaciones.map((notificacion) => {
                const idInstrumento = notificacion.id_instrumento;
                const relativo = formatRelativo(notificacion.fecha_generacion);

                return (
                  <PressableA11y
                    key={notificacion.id_notificacion}
                    accessibilityRole={idInstrumento === null ? 'text' : 'button'}
                    accessibilityLabel={[
                      notificacion.leida ? null : 'Sin leer',
                      notificacion.mensaje,
                      relativo,
                      idInstrumento !== null ? `Convenio número ${idInstrumento}` : null,
                    ]
                      .filter(Boolean)
                      .join('. ')}
                    accessibilityHint={
                      idInstrumento === null ? undefined : 'Marca como leída y abre el convenio'
                    }
                    disabled={idInstrumento === null}
                    onPress={() => void abrirNotificacion(notificacion.id_notificacion, idInstrumento)}
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
                      {/* El estado no depende solo del color del borde: también se escribe. */}
                      {notificacion.leida ? '' : 'Nueva · '}
                      {relativo}
                      {idInstrumento !== null ? ` · Convenio #${idInstrumento}` : ''}
                    </Txt>
                  </PressableA11y>
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
  marcarTodas: {
    padding: SgcSpacing.md,
    borderRadius: SgcRadius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'transparent',
    alignSelf: 'flex-start',
    minHeight: AREA_TACTIL_MINIMA,
  },
});
