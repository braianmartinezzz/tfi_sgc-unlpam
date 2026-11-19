import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EstadoBadge } from '@/components/panel/estado-badge';
import { Txt } from '@/components/panel/txt';
import { Destinos } from '@/constants/navigation';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useConvenios } from '@/hooks/use-convenios';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { formatFecha } from '@/lib/format';
import { obtenerUsuarioActual } from '@/services/auth';
import { listarUnidades } from '@/services/convenios';
import type { ConvenioLista, UnidadAcademica } from '@/types/convenio';

type Subtipo = 'todos' | 'marco' | 'especifico';

const TODOS_LOS_ESTADOS = [
  'Borrador',
  'EnCorreccion',
  'Revisión',
  'Aprobado',
  'Firmado',
  'Rescindido',
  'Rechazado',
  'Archivado',
];

/** Estados que puede ver cada rol (igual que la web; la API igual lo valida). */
const ESTADOS_POR_ROL: Record<string, string[]> = {
  Administración: TODOS_LOS_ESTADOS,
  Gestión: ['Borrador', 'EnCorreccion', 'Revisión', 'Aprobado', 'Firmado', 'Rescindido'],
  Revisión: ['Revisión', 'EnCorreccion', 'Aprobado', 'Rechazado', 'Rescindido', 'Archivado'],
  Consulta: ['Firmado', 'Rescindido', 'Archivado'],
};

function useDebounce<T>(valor: T, ms: number): T {
  const [retrasado, setRetrasado] = useState(valor);

  useEffect(() => {
    const id = setTimeout(() => setRetrasado(valor), ms);
    return () => clearTimeout(id);
  }, [valor, ms]);

  return retrasado;
}

export default function ConveniosScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  // Si se llega desde el buscador del panel, viene como /convenios?buscar=texto
  // /convenios?filtros=1 abre la lista con el panel de filtros desplegado (búsqueda avanzada).
  const { buscar: buscarInicial, filtros: filtrosInicial } = useLocalSearchParams<{
    buscar?: string;
    filtros?: string;
  }>();

  const [texto, setTexto] = useState(buscarInicial ?? '');
  const [estadoSel, setEstadoSel] = useState<string | null>(null);
  const [subtipo, setSubtipo] = useState<Subtipo>('todos');
  const [facultadSel, setFacultadSel] = useState<number | null>(null);
  const [filtrosAbiertos, setFiltrosAbiertos] = useState(filtrosInicial === '1');

  const [rol, setRol] = useState<string | null>(null);
  const [puedeFiltrarFacultad, setPuedeFiltrarFacultad] = useState(false);
  const [unidades, setUnidades] = useState<UnidadAcademica[]>([]);

  // El rol define qué estados se ofrecen y si se puede filtrar por facultad.
  useEffect(() => {
    let activo = true;

    obtenerUsuarioActual()
      .then((me) => {
        if (!activo) return;
        const nombreRol = me.rol?.nombre ?? null;
        setRol(nombreRol);
        setPuedeFiltrarFacultad(
          nombreRol === 'Administración' ||
            ((nombreRol === 'Revisión' || nombreRol === 'Consulta') && me.alcance_global === true),
        );
      })
      .catch(() => {
        // Sin el rol se ofrecen todos los estados; la API filtra lo que no corresponde.
      });

    return () => {
      activo = false;
    };
  }, []);

  useEffect(() => {
    if (!puedeFiltrarFacultad) return;
    let activo = true;

    listarUnidades()
      .then((lista) => activo && setUnidades(lista))
      .catch(() => activo && setUnidades([]));

    return () => {
      activo = false;
    };
  }, [puedeFiltrarFacultad]);

  const estadosVisibles = (rol && ESTADOS_POR_ROL[rol]) || TODOS_LOS_ESTADOS;
  const buscar = useDebounce(texto.trim(), 400);

  const filtros = useMemo(
    () => ({
      ...(buscar ? { buscar } : {}),
      ...(estadoSel ? { estado: estadoSel } : {}),
      ...(subtipo !== 'todos' ? { es_marco: subtipo === 'marco' } : {}),
      ...(facultadSel !== null ? { id_unidad_academica: facultadSel } : {}),
    }),
    [buscar, estadoSel, subtipo, facultadSel],
  );

  const { items, total, estado, error, cargandoMas, actualizando, cargarMas, actualizar } =
    useConvenios(filtros);

  const filtrosActivos =
    (estadoSel ? 1 : 0) + (subtipo !== 'todos' ? 1 : 0) + (facultadSel !== null ? 1 : 0);
  const hayAlgunFiltro = filtrosActivos > 0 || texto.trim().length > 0;

  const limpiar = () => {
    setTexto('');
    setEstadoSel(null);
    setSubtipo('todos');
    setFacultadSel(null);
  };

  const volver = () => (router.canGoBack() ? router.back() : router.replace('/dashboard'));

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <View
        style={[
          styles.encabezado,
          { backgroundColor: tema.topbar, paddingTop: insets.top + SgcSpacing.sm },
        ]}>
        <View style={styles.fila}>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Volver"
            onPress={volver}
            hitSlop={8}
            style={({ pressed }) => pressed && { opacity: 0.6 }}>
            <Txt variante="subtitulo" color="onTopbar">
              ‹ Volver
            </Txt>
          </Pressable>
          <Txt variante="subtitulo" color="onTopbar">
            Convenios
          </Txt>
        </View>

        <View style={styles.fila}>
          <TextInput
            accessibilityLabel="Buscar convenios por título"
            value={texto}
            onChangeText={setTexto}
            returnKeyType="search"
            autoCorrect={false}
            placeholder="Buscar por título…"
            placeholderTextColor={tema.fieldPlaceholder}
            style={[styles.buscador, { backgroundColor: tema.field, color: tema.onField }]}
          />
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Mostrar u ocultar filtros"
            onPress={() => setFiltrosAbiertos((abierto) => !abierto)}
            style={({ pressed }) => [
              styles.botonFiltros,
              { borderColor: filtrosActivos > 0 ? tema.brand : tema.fieldPlaceholder },
              filtrosActivos > 0 && { backgroundColor: tema.brand },
              pressed && { opacity: 0.7 },
            ]}>
            <Txt variante="detalle" color="onTopbar" negrita>
              Filtros{filtrosActivos > 0 ? ` (${filtrosActivos})` : ''}
            </Txt>
          </Pressable>
        </View>

        {filtrosAbiertos ? (
          <View style={styles.filtros}>
            <GrupoChips etiqueta="Estado">
              <Chip texto="Todos" activo={estadoSel === null} onPress={() => setEstadoSel(null)} />
              {estadosVisibles.map((opcion) => (
                <Chip
                  key={opcion}
                  texto={opcion}
                  activo={estadoSel === opcion}
                  onPress={() => setEstadoSel(estadoSel === opcion ? null : opcion)}
                />
              ))}
            </GrupoChips>

            <GrupoChips etiqueta="Tipo">
              <Chip texto="Todos" activo={subtipo === 'todos'} onPress={() => setSubtipo('todos')} />
              <Chip texto="Marco" activo={subtipo === 'marco'} onPress={() => setSubtipo('marco')} />
              <Chip
                texto="Específico"
                activo={subtipo === 'especifico'}
                onPress={() => setSubtipo('especifico')}
              />
            </GrupoChips>

            {puedeFiltrarFacultad && unidades.length > 0 ? (
              <GrupoChips etiqueta="Facultad">
                <Chip
                  texto="Todas"
                  activo={facultadSel === null}
                  onPress={() => setFacultadSel(null)}
                />
                {unidades.map((unidad) => (
                  <Chip
                    key={unidad.id_unidad_academica}
                    texto={unidad.sigla}
                    activo={facultadSel === unidad.id_unidad_academica}
                    onPress={() =>
                      setFacultadSel(
                        facultadSel === unidad.id_unidad_academica ? null : unidad.id_unidad_academica,
                      )
                    }
                  />
                ))}
              </GrupoChips>
            ) : null}
          </View>
        ) : null}
      </View>

      <View style={styles.resumen}>
        <Txt variante="detalle" color="textSecondary">
          {estado === 'listo' ? `${total} convenio${total === 1 ? '' : 's'}` : ' '}
        </Txt>
        {hayAlgunFiltro ? (
          <Pressable accessibilityRole="button" onPress={limpiar} hitSlop={8}>
            <Txt variante="detalle" negrita style={{ color: tema.brandText }}>
              Limpiar filtros
            </Txt>
          </Pressable>
        ) : null}
      </View>

      {estado === 'cargando' ? (
        <View style={styles.centrado}>
          <ActivityIndicator color={tema.brand} />
          <Txt variante="detalle" color="textSecondary">
            Cargando convenios…
          </Txt>
        </View>
      ) : null}

      {estado === 'error' ? (
        <View style={styles.lista}>
          <Card titulo="No se pudieron cargar los convenios" bordeSuperior={tema.danger}>
            <Txt color="textSecondary">{error}</Txt>
            <Boton texto="Reintentar" onPress={actualizar} />
          </Card>
        </View>
      ) : null}

      {estado === 'listo' ? (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id_instrumento)}
          renderItem={({ item }) => (
            <ConvenioItem
              convenio={item}
              onPress={() => router.push(Destinos.convenio(item.id_instrumento))}
            />
          )}
          contentContainerStyle={styles.lista}
          ItemSeparatorComponent={() => <View style={{ height: SgcSpacing.md }} />}
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="on-drag"
          onEndReached={cargarMas}
          onEndReachedThreshold={0.4}
          refreshControl={
            <RefreshControl
              refreshing={actualizando}
              onRefresh={actualizar}
              tintColor={tema.brand}
              colors={[tema.brand]}
            />
          }
          ListEmptyComponent={
            <Card>
              <EstadoVacio texto="No hay convenios que coincidan con la búsqueda y los filtros." />
              {hayAlgunFiltro ? <Boton texto="Limpiar filtros" onPress={limpiar} /> : null}
            </Card>
          }
          ListFooterComponent={
            cargandoMas ? (
              <ActivityIndicator color={tema.brand} style={styles.pie} />
            ) : error ? (
              <View style={styles.pie}>
                <Txt variante="detalle" color="textSecondary">
                  No se pudo cargar más: {error}
                </Txt>
                <Boton texto="Reintentar" onPress={cargarMas} />
              </View>
            ) : undefined
          }
        />
      ) : null}
    </View>
  );
}

function ConvenioItem({ convenio, onPress }: { convenio: ConvenioLista; onPress: () => void }) {
  const tema = useSgcTheme();
  const instrumento = convenio.instrumento;
  const subtipo = convenio.marco ? 'Marco' : convenio.especifico ? 'Específico' : null;
  const firmantes = convenio.instituciones_firmantes.map((i) => i.razon_social).join(', ');
  const detalle = [subtipo, convenio.tipo, convenio.unidad_academica?.sigla]
    .filter(Boolean)
    .join(' · ');

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Abrir convenio ${instrumento?.titulo ?? convenio.id_instrumento}`}
      onPress={onPress}
      style={({ pressed }) => [
        styles.item,
        { backgroundColor: tema.surface, borderColor: tema.border },
        pressed && { opacity: 0.85 },
      ]}>
      <View style={styles.itemCabecera}>
        <Txt variante="detalle" color="textSecondary">
          #{convenio.id_instrumento}
        </Txt>
        <EstadoBadge estado={instrumento?.estado ?? 'Sin estado'} />
      </View>
      <Txt variante="subtitulo" numberOfLines={2}>
        {instrumento?.titulo ?? 'Sin título'}
      </Txt>
      {detalle ? (
        <Txt variante="detalle" color="textSecondary">
          {detalle}
        </Txt>
      ) : null}
      {firmantes ? (
        <Txt variante="detalle" color="textSecondary" numberOfLines={1}>
          {firmantes}
        </Txt>
      ) : null}
      {instrumento?.vencimiento ? (
        <Txt variante="detalle" color="textSecondary">
          Vence el {formatFecha(instrumento.vencimiento)}
        </Txt>
      ) : null}
    </Pressable>
  );
}

function GrupoChips({ etiqueta, children }: { etiqueta: string; children: React.ReactNode }) {
  return (
    <View style={styles.grupo}>
      <Txt variante="etiqueta" color="onTopbar">
        {etiqueta}
      </Txt>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
        {children}
      </ScrollView>
    </View>
  );
}

function Chip({ texto, activo, onPress }: { texto: string; activo: boolean; onPress: () => void }) {
  const tema = useSgcTheme();

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: activo }}
      onPress={onPress}
      style={({ pressed }) => [
        styles.chip,
        {
          borderColor: activo ? tema.brand : tema.fieldPlaceholder,
          backgroundColor: activo ? tema.brand : 'transparent',
        },
        pressed && { opacity: 0.8 },
      ]}>
      <Txt variante="detalle" negrita style={{ color: tema.onTopbar }}>
        {texto}
      </Txt>
    </Pressable>
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
  encabezado: {
    paddingHorizontal: SgcSpacing.lg,
    paddingBottom: SgcSpacing.md,
    gap: SgcSpacing.md,
  },
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: SgcSpacing.sm,
  },
  buscador: {
    flex: 1,
    height: 44,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.md,
    fontSize: 15,
  },
  botonFiltros: {
    height: 44,
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    paddingHorizontal: SgcSpacing.md,
    justifyContent: 'center',
  },
  filtros: { gap: SgcSpacing.md },
  grupo: { gap: SgcSpacing.xs },
  chips: { gap: SgcSpacing.sm },
  chip: {
    borderWidth: 1,
    borderRadius: SgcRadius.pill,
    paddingHorizontal: SgcSpacing.md,
    paddingVertical: 6,
  },
  resumen: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: SgcSpacing.lg,
    paddingVertical: SgcSpacing.sm,
  },
  lista: {
    padding: SgcSpacing.lg,
    paddingTop: SgcSpacing.sm,
    paddingBottom: SgcSpacing.xl * 2,
    width: '100%',
    maxWidth: 800,
    alignSelf: 'center',
  },
  centrado: {
    alignItems: 'center',
    gap: SgcSpacing.sm,
    padding: SgcSpacing.xl,
  },
  item: {
    borderWidth: 1,
    borderRadius: SgcRadius.md,
    padding: SgcSpacing.lg,
    gap: SgcSpacing.xs,
  },
  itemCabecera: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  boton: {
    alignItems: 'center',
    borderRadius: SgcRadius.md,
    paddingVertical: SgcSpacing.md,
  },
  pie: {
    paddingVertical: SgcSpacing.lg,
    gap: SgcSpacing.sm,
    alignItems: 'center',
  },
});
