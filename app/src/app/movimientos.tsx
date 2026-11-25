import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { EstadoBadge } from '@/components/panel/estado-badge';
import { OfflineBanner } from '@/components/panel/offline-banner';
import { Txt } from '@/components/panel/txt';
import { Destinos } from '@/constants/navigation';
import { SgcSpacing } from '@/constants/sgc-theme';
import { usePanel } from '@/hooks/use-panel';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatRelativo } from '@/lib/format';
import {
  leerMovimientosConsultados,
  registrarMovimientoConsultado,
  type MovimientoConsultado,
} from '@/lib/movimientos-storage';
import type { MovimientoPanel } from '@/types/panel';

export default function MovimientosScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const { panel, estado, sinConexion, actualizado, actualizando, error, actualizar } = usePanel();
  const movimientos = panel?.ultimos_movimientos ?? [];
  const [consultados, setConsultados] = useState<MovimientoConsultado[]>([]);

  // Al volver de un convenio (o de otro pantalla) el historial queda actualizado.
  useFocusEffect(
    useCallback(() => {
      void leerMovimientosConsultados().then(setConsultados);
    }, []),
  );

  /** Registra la consulta y navega al convenio desde cualquier lista. */
  const abrirConvenio = useCallback(
    (movimiento: MovimientoPanel) => {
      void registrarMovimientoConsultado(movimiento);
      router.push(Destinos.convenio(movimiento.id_instrumento));
    },
    [router],
  );

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Movimientos recientes" />
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
          {consultados.length > 0 ? (
            <>
              <Txt variante="subtitulo">Consultados recientemente</Txt>
              {consultados.map((movimiento) => (
                <Pressable
                  key={`consultado-${movimiento.id_instrumento}`}
                  accessibilityRole="button"
                  onPress={() => abrirConvenio(movimiento)}
                  style={({ pressed }) => [
                    styles.fila,
                    { backgroundColor: tema.surface, borderColor: tema.border },
                    pressed && { backgroundColor: tema.rowAlternate },
                  ]}>
                  <Txt negrita>{movimiento.titulo ?? `Convenio #${movimiento.id_instrumento}`}</Txt>
                  <View style={styles.estados}>
                    <EstadoBadge estado={movimiento.estado_nuevo} />
                  </View>
                  <Txt variante="etiqueta" color="textSecondary">
                    {formatRelativo(movimiento.registrado_el)}
                    {movimiento.usuario ? ` · ${movimiento.usuario}` : ''}
                  </Txt>
                </Pressable>
              ))}
            </>
          ) : null}

          <Txt variante="subtitulo">Últimos movimientos</Txt>

          {estado === 'cargando' ? (
            <View style={styles.centrado}>
              <ActivityIndicator color={tema.brand} />
              <Txt variante="detalle" color="textSecondary">Cargando movimientos…</Txt>
            </View>
          ) : null}

          {estado === 'error' ? (
            <Card titulo="No se pudieron cargar los movimientos" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">{error}</Txt>
            </Card>
          ) : null}

          {estado === 'listo' && movimientos.length === 0 ? (
            <Card><EstadoVacio texto="Todavía no hay movimientos registrados." /></Card>
          ) : null}

          {estado === 'listo' && movimientos.map((movimiento, indice) => (
            <Pressable
              key={`${movimiento.id_instrumento}-${movimiento.fecha_hora}-${indice}`}
              accessibilityRole="button"
              onPress={() => abrirConvenio(movimiento)}
              style={({ pressed }) => [
                styles.fila,
                { backgroundColor: tema.surface, borderColor: tema.border },
                pressed && { backgroundColor: tema.rowAlternate },
              ]}>
              <Txt negrita>{movimiento.titulo ?? `Convenio #${movimiento.id_instrumento}`}</Txt>
              <View style={styles.estados}>
                {movimiento.estado_anterior ? <EstadoBadge estado={movimiento.estado_anterior} /> : null}
                {movimiento.estado_anterior ? <Txt variante="detalle" color="textSecondary">→</Txt> : null}
                <EstadoBadge estado={movimiento.estado_nuevo} />
              </View>
              {movimiento.motivo ? (
                <Txt variante="detalle" color="textSecondary">{movimiento.motivo}</Txt>
              ) : null}
              <Txt variante="etiqueta" color="textSecondary">
                {formatRelativo(movimiento.fecha_hora)}
                {movimiento.usuario ? ` · ${movimiento.usuario}` : ''}
              </Txt>
            </Pressable>
          ))}
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
  fila: { borderWidth: 1, borderRadius: 8, padding: SgcSpacing.md, gap: SgcSpacing.sm },
  estados: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: SgcSpacing.sm },
});
