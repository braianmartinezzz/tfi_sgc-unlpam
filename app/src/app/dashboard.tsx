import { useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { AccesosRapidos } from '@/components/panel/accesos-rapidos';
import { Card } from '@/components/panel/card';
import { PorEstadoCard, PorUnidadCard } from '@/components/panel/distribucion-cards';
import { MovimientosCard } from '@/components/panel/movimientos-card';
import { NotificacionesCard } from '@/components/panel/notificaciones-card';
import { OfflineBanner } from '@/components/panel/offline-banner';
import { PanelHeader } from '@/components/panel/panel-header';
import { PendientesCard } from '@/components/panel/pendientes-card';
import { ProfileMenu } from '@/components/panel/profile-menu';
import { StatBox } from '@/components/panel/stat-box';
import { Txt } from '@/components/panel/txt';
import { Destinos, Disponible } from '@/constants/navigation';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { usePanel } from '@/hooks/use-panel';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { getToken } from '@/lib/auth-storage';
import { formatFecha, formatHora } from '@/lib/format';
import { cerrarSesion } from '@/services/auth';

/** Roles que pueden registrar la firma de un convenio aprobado. */
const ROLES_QUE_FIRMAN = ['Gestión', 'Administración'];

export default function PanelScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const { me, panel, actualizado, estado, sinConexion, actualizando, error, actualizar } = usePanel();
  const [menuAbierto, setMenuAbierto] = useState(false);

  // Sin token no hay panel: se vuelve al inicio de sesión.
  useEffect(() => {
    let activo = true;

    getToken().then((token) => {
      if (activo && !token) router.replace('/');
    });

    return () => {
      activo = false;
    };
  }, [router]);

  const puedeFirmar = ROLES_QUE_FIRMAN.includes(me?.rol?.nombre ?? '');

  const irA = (destino: Href) => {
    setMenuAbierto(false);
    router.push(destino);
  };

  /** Devuelve el handler solo si el destino existe; si no, la UI lo muestra como "Próximamente". */
  const siDisponible = (disponible: boolean, destino: Href) =>
    disponible ? () => irA(destino) : undefined;

  const salir = async () => {
    setMenuAbierto(false);
    await cerrarSesion();
    router.replace('/');
  };

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <PanelHeader
        nombreUsuario={me?.nombre ?? null}
        sinConexion={sinConexion}
        onBuscar={(texto) => irA(Destinos.buscarConvenios(texto))}
        onBusquedaAvanzada={() => irA(Destinos.busquedaAvanzada())}
        onAbrirPerfil={() => setMenuAbierto(true)}
      />

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
          <View>
            <Txt variante="titulo">Panel</Txt>
            {me ? (
              <Txt variante="detalle" color="textSecondary">
                {[me.rol?.nombre, me.unidad_academica?.nombre].filter(Boolean).join(' · ')}
              </Txt>
            ) : null}
          </View>

          {estado === 'cargando' ? (
            <View style={styles.centrado}>
              <ActivityIndicator color={tema.brand} />
              <Txt variante="detalle" color="textSecondary">
                Cargando el panel…
              </Txt>
            </View>
          ) : null}

          {estado === 'error' ? (
            <Card titulo="No se pudo cargar el panel" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">{error}</Txt>
              <Txt variante="detalle" color="textSecondary">
                Deslizá hacia abajo para reintentar.
              </Txt>
            </Card>
          ) : null}

          {error && estado === 'listo' && !sinConexion ? (
            <Card bordeSuperior={tema.warning}>
              <Txt variante="detalle" color="textSecondary">
                No se pudieron actualizar los datos: {error}
              </Txt>
            </Card>
          ) : null}

          {panel && estado === 'listo' ? (
            <>
              {/* En modo offline solo se muestran los pendientes de firma guardados. */}
              <PendientesCard
                pendientes={panel.pendientes_firma}
                sinConexion={sinConexion}
                onAbrirFirmador={() => irA(Destinos.firmador())}
                onAbrirConvenio={(id) => irA(Destinos.convenio(id))}
              />

              {!sinConexion ? (
                <>
                  <View style={styles.grilla}>
                    <StatBox
                      valor={panel.resumen.firmados_ultimo_anio}
                      etiqueta="Firmados en el último año"
                      color={tema.success}
                    />
                    <StatBox
                      valor={panel.resumen.vigentes}
                      etiqueta="Vigentes"
                      color={tema.info}
                    />
                    <StatBox
                      valor={panel.resumen.en_tramite}
                      etiqueta="En trámite"
                      color={tema.warning}
                      nota="Elaboración, revisión o aprobación"
                    />
                    <StatBox
                      valor={panel.resumen.en_preaviso}
                      etiqueta="En preaviso"
                      color={tema.brand}
                      nota={
                        panel.resumen.vencidos > 0
                          ? `${panel.resumen.vencidos} vencido(s) sin archivar`
                          : undefined
                      }
                    />
                  </View>

                  <NotificacionesCard
                    alertas={panel.alertas}
                    notificaciones={panel.notificaciones.items}
                    noLeidas={panel.notificaciones.no_leidas}
                    onAbrirConvenio={(id) => irA(Destinos.convenio(id))}
                    onVerTodas={siDisponible(Disponible.notificaciones, Destinos.notificaciones())}
                  />

                  <MovimientosCard
                    movimientos={panel.ultimos_movimientos}
                    onAbrirConvenio={(id) => irA(Destinos.convenio(id))}
                  />

                  <AccesosRapidos
                    sinConexion={sinConexion}
                    puedeFirmar={puedeFirmar}
                    onEscanearQr={siDisponible(Disponible.escanearQr, Destinos.escanearQr())}
                    onFirmador={() => irA(Destinos.firmador())}
                    onBusquedaAvanzada={() => irA(Destinos.busquedaAvanzada())}
                    onAyuda={siDisponible(Disponible.ayuda, Destinos.ayuda())}
                  />

                  <PorEstadoCard porEstado={panel.resumen.por_estado} />
                  <PorUnidadCard unidades={panel.por_unidad} />
                </>
              ) : null}

              {actualizado ? (
                <Txt variante="etiqueta" color="textSecondary" style={styles.pie}>
                  Actualizado el {formatFecha(actualizado.toISOString())} a las {formatHora(actualizado)}
                </Txt>
              ) : null}
            </>
          ) : null}

          {sinConexion && !panel && estado === 'listo' ? (
            <Card bordeSuperior={tema.warning}>
              <Txt color="textSecondary">
                No hay datos guardados en este dispositivo. Conectate a internet para ver el panel.
              </Txt>
            </Card>
          ) : null}
        </View>
      </ScrollView>

      <ProfileMenu
        visible={menuAbierto}
        usuario={me}
        onCerrar={() => setMenuAbierto(false)}
        onMiPerfil={siDisponible(Disponible.miPerfil, Destinos.miPerfil())}
        onRegistrarIdentidad={siDisponible(Disponible.registrarIdentidad, Destinos.registrarIdentidad())}
        onAyuda={siDisponible(Disponible.ayuda, Destinos.ayuda())}
        onCerrarSesion={salir}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  pantalla: {
    flex: 1,
  },
  contenido: {
    padding: SgcSpacing.lg,
    paddingBottom: SgcSpacing.xl * 2,
  },
  columna: {
    width: '100%',
    maxWidth: 800,
    alignSelf: 'center',
    gap: SgcSpacing.lg,
  },
  grilla: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: SgcSpacing.md,
  },
  centrado: {
    alignItems: 'center',
    gap: SgcSpacing.sm,
    padding: SgcSpacing.xl,
    borderRadius: SgcRadius.md,
  },
  pie: {
    textAlign: 'center',
  },
});
