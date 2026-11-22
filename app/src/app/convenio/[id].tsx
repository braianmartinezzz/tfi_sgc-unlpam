import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';

import { Card } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { EstadoBadge } from '@/components/panel/estado-badge';
import { Txt } from '@/components/panel/txt';
import { Destinos } from '@/constants/navigation';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatFecha } from '@/lib/format';
import { ApiError } from '@/services/api-client';
import { obtenerConvenio } from '@/services/convenios';
import { listarPendientesFirma } from '@/services/firma';
import type { ConvenioDetalle } from '@/types/convenio';
import type { PendienteFirmaCompleto } from '@/types/firma';

type Datos = {
  convenio: ConvenioDetalle;
  /** Presente solo si este convenio está esperando la firma del usuario actual. */
  pendiente: PendienteFirmaCompleto | null;
};

export default function ConvenioScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const { id } = useLocalSearchParams<{ id: string }>();
  const idInstrumento = Number(id);
  const idValido = Number.isInteger(idInstrumento) && idInstrumento > 0;

  const [datos, setDatos] = useState<Datos | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [actualizando, setActualizando] = useState(false);

  const cargar = useCallback(async () => {
    if (!idValido) return;

    try {
      const [convenio, pendientes] = await Promise.all([
        obtenerConvenio(idInstrumento),
        // Si falla, simplemente no se ofrece el botón de firma.
        listarPendientesFirma().catch(() => [] as PendienteFirmaCompleto[]),
      ]);

      setDatos({
        convenio,
        pendiente: pendientes.find((p) => p.id_instrumento === idInstrumento) ?? null,
      });
      setError(null);
    } catch (causa) {
      setError(
        causa instanceof ApiError && causa.status === 404
          ? 'No se encontró el convenio, o no tenés permiso para verlo.'
          : causa instanceof Error
            ? causa.message
            : 'No se pudo cargar el convenio.',
      );
    }
  }, [idInstrumento, idValido]);

  // Se recarga cada vez que la pantalla vuelve a estar en foco (por ejemplo, tras firmar).
  useFocusEffect(
    useCallback(() => {
      void cargar();
    }, [cargar]),
  );

  const actualizar = async () => {
    setActualizando(true);
    await cargar();
    setActualizando(false);
  };

  const convenio = datos?.convenio ?? null;
  const instrumento = convenio?.instrumento ?? null;
  const subtipo = convenio?.marco ? 'Marco' : convenio?.especifico ? 'Específico' : null;
  const pendiente = datos?.pendiente ?? null;

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo={idValido ? `Convenio #${idInstrumento}` : 'Convenio'} />

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
          {!idValido ? (
            <Card titulo="Convenio inválido" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">El enlace no corresponde a un convenio.</Txt>
            </Card>
          ) : null}

          {idValido && !convenio && !error ? (
            <View style={styles.centrado}>
              <ActivityIndicator color={tema.brand} />
              <Txt variante="detalle" color="textSecondary">
                Cargando convenio…
              </Txt>
            </View>
          ) : null}

          {error ? (
            <Card titulo="No se pudo cargar el convenio" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">{error}</Txt>
              <Boton texto="Reintentar" onPress={actualizar} />
            </Card>
          ) : null}

          {convenio ? (
            <>
              <Card bordeSuperior={tema.brand}>
                <EstadoBadge estado={instrumento?.estado ?? 'Sin estado'} />
                <Txt variante="titulo">{instrumento?.titulo ?? 'Sin título'}</Txt>

                <Dato etiqueta="Tipo" valor={[subtipo, convenio.tipo].filter(Boolean).join(' · ')} />
                <Dato
                  etiqueta="Unidad académica"
                  valor={
                    convenio.unidad_academica
                      ? `${convenio.unidad_academica.nombre} (${convenio.unidad_academica.sigla})`
                      : null
                  }
                />
                <Dato
                  etiqueta="Vencimiento"
                  valor={instrumento?.vencimiento ? formatFecha(instrumento.vencimiento) : null}
                />
              </Card>

              {convenio.instituciones_firmantes.length > 0 ? (
                <Card titulo="Instituciones firmantes">
                  {convenio.instituciones_firmantes.map((institucion) => (
                    <Txt key={institucion.id_institucion}>{institucion.razon_social}</Txt>
                  ))}
                </Card>
              ) : null}

              {pendiente ? (
                <Card titulo="Pendiente de tu firma" bordeSuperior={tema.warning}>
                  {pendiente.firmantes.map((firmante) => (
                    <Txt key={firmante.id_persona} variante="detalle" color="textSecondary">
                      {firmante.nombre} {firmante.apellido}
                      {firmante.firmado ? ' · Firmado' : ' · Pendiente'}
                    </Txt>
                  ))}
                  <Boton
                    texto="Ir a firmar"
                    onPress={() => router.push(Destinos.firmador(pendiente.id_instrumento))}
                  />
                </Card>
              ) : null}
            </>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

function Dato({ etiqueta, valor }: { etiqueta: string; valor: string | null }) {
  if (!valor) return null;

  return (
    <View style={styles.dato}>
      <Txt variante="etiqueta" color="textSecondary">
        {etiqueta}
      </Txt>
      <Txt>{valor}</Txt>
    </View>
  );
}

function Boton({ texto, onPress }: { texto: string; onPress: () => void }) {
  const tema = useSgcTheme();

  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.boton, { backgroundColor: tema.brand }, pressed && { opacity: 0.85 }]}>
      <Txt negrita color="onBrand">
        {texto}
      </Txt>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  contenido: { padding: SgcSpacing.lg, paddingBottom: SgcSpacing.xl * 2 },
  columna: { width: '100%', maxWidth: 800, alignSelf: 'center', gap: SgcSpacing.lg },
  centrado: { alignItems: 'center', gap: SgcSpacing.sm, padding: SgcSpacing.xl },
  dato: { gap: 2 },
  boton: { borderRadius: 6, paddingVertical: SgcSpacing.md, alignItems: 'center' },
});
