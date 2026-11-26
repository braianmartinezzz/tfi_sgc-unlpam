import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  BackHandler,
  Linking,
  RefreshControl,
  ScrollView,
  StyleSheet,
  TextInput,
  View,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as LocalAuthentication from 'expo-local-authentication';
import * as Sharing from 'expo-sharing';
import SignatureCanvas, {
  type SignatureViewRef,
} from 'react-native-signature-canvas';
import NetInfo from '@react-native-community/netinfo';

import { ConflictoFirmaCard } from '@/components/firma/conflicto-firma-card';
import { Card, EstadoVacio } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { PressableA11y } from '@/components/panel/pressable-a11y';
import { Txt } from '@/components/panel/txt';
import { AREA_TACTIL_MINIMA } from '@/constants/accesibilidad';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { anunciar } from '@/lib/accesibilidad';
import { detectarConflicto, type ConflictoFirma } from '@/lib/concurrencia-firma';
import { ApiError } from '@/services/api-client';
import { confirmarConBiometria } from '@/services/biometria-login';
import { UbicacionError, obtenerUbicacionParaFirma } from '@/services/ubicacion-firma';
import { crearComentarioFirma, descargarConstanciaFirma, descargarDocumentoFirma, listarPendientesFirma, registrarFirma } from '@/services/firma';
import type { PendienteFirmaCompleto } from '@/types/firma';
import type { ComentarioFirmador } from '@/types/firmador-comentarios';

export default function FirmadorScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  // Si se llega desde la ficha de un convenio (/firmador?id=7) se abre directo sobre ese convenio.
  const { id } = useLocalSearchParams<{ id?: string }>();
  const idInicial = id && Number.isInteger(Number(id)) ? Number(id) : null;
  const vinoDeFicha = idInicial !== null;
  const firmaRef = useRef<SignatureViewRef>(null);

  const [pendientes, setPendientes] = useState<PendienteFirmaCompleto[]>([]);
  const [seleccionado, setSeleccionado] = useState<PendienteFirmaCompleto | null>(null);
  const [trazo, setTrazo] = useState<string | null>(null);
  const [estado, setEstado] = useState<'cargando' | 'listo' | 'error'>('cargando');
  const [error, setError] = useState<string | null>(null);
  const [actualizando, setActualizando] = useState(false);
  const [firmando, setFirmando] = useState(false);
  const [comentarios, setComentarios] = useState<ComentarioFirmador[]>([]);
  const [nuevoComentario, setNuevoComentario] = useState('');
  const [conflicto, setConflicto] = useState<ConflictoFirma | null>(null);
  // Permite comparar contra lo que la persona tiene abierto sin depender del cierre de cada callback.
  const seleccionadoRef = useRef<PendienteFirmaCompleto | null>(null);

  useEffect(() => {
    seleccionadoRef.current = seleccionado;
  }, [seleccionado]);

  // Guardar la firma no cambia nada a la vista salvo habilitar "Confirmar": se anuncia en voz alta.
  const huboTrazo = useRef(false);
  useEffect(() => {
    if (trazo && !huboTrazo.current) anunciar('Firma guardada. Ya podés confirmar.');
    huboTrazo.current = trazo !== null;
  }, [trazo]);

  /**
   * Aplica la lista vigente del servidor. Si el convenio abierto cambió, NO se pisa en
   * silencio (la firma dibujada quedaría atada a datos que la persona nunca vio): se
   * muestra el conflicto y ella decide. Devuelve true si se puede seguir sin riesgo.
   */
  const aplicarLista = useCallback(
    (lista: PendienteFirmaCompleto[], mensajeServidor?: string): boolean => {
      setPendientes(lista);

      const local = seleccionadoRef.current;
      if (!local) return true;

      const vigente = lista.find((item) => item.id_instrumento === local.id_instrumento);
      const detectado = detectarConflicto(local, vigente, mensajeServidor);

      if (detectado) {
        setConflicto(detectado);
        anunciar(`Conflicto de versión. ${detectado.mensaje}`);
        return false;
      }

      if (vigente) setSeleccionado(vigente);
      return true;
    },
    [],
  );

  /** Vuelve a pedir el estado al servidor y avisa si lo abierto quedó desactualizado. */
  const verificarVersion = useCallback(
    async (mensajeServidor?: string): Promise<boolean> =>
      aplicarLista(await listarPendientesFirma(), mensajeServidor),
    [aplicarLista],
  );

  const cargar = useCallback(async (mostrarCarga = true) => {
    if (mostrarCarga) setEstado('cargando');
    setError(null);

    try {
      aplicarLista(await listarPendientesFirma());
      setEstado('listo');
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'No se pudieron cargar los pendientes.');
      setEstado('error');
    }
  }, [aplicarLista]);

  useEffect(() => {
    let activo = true;

    void (async () => {
      try {
        const lista = await listarPendientesFirma();
        if (activo) {
          setPendientes(lista);
          if (idInicial !== null) {
            setSeleccionado(lista.find((item) => item.id_instrumento === idInicial) ?? null);
          }
          setEstado('listo');
        }
      } catch (exception) {
        if (activo) {
          setError(exception instanceof Error ? exception.message : 'No se pudieron cargar los pendientes.');
          setEstado('error');
        }
      }
    })();

    return () => {
      activo = false;
    };
  }, [idInicial]);

  const actualizar = async () => {
    setActualizando(true);
    await cargar(false);
    setActualizando(false);
  };

  // Desde la lista se vuelve a la lista; desde la ficha, a la ficha.
  const salirDeSeleccion = useCallback(() => {
    setConflicto(null);
    if (vinoDeFicha) {
      router.back();
    } else {
      setSeleccionado(null);
      setTrazo(null);
    }
  }, [router, vinoDeFicha]);

  // Botón atrás de Android: con un convenio abierto desde la lista, vuelve a la lista
  // en vez de salir de todo el firmador.
  useEffect(() => {
    if (!seleccionado || vinoDeFicha) return;

    const suscripcion = BackHandler.addEventListener('hardwareBackPress', () => {
      salirDeSeleccion();
      return true;
    });

    return () => suscripcion.remove();
  }, [seleccionado, vinoDeFicha, salirDeSeleccion]);

  const seleccionar = (pendiente: PendienteFirmaCompleto) => {
    setConflicto(null);
    setSeleccionado(pendiente);
    setTrazo(null);
    setNuevoComentario('');
  };

  // Resolución de un conflicto de versión ------------------------------------------------

  /** Toma la versión vigente y empieza de cero: se borran la firma y el comentario en borrador. */
  const recargarConflicto = () => {
    if (!conflicto?.actual) return;
    firmaRef.current?.clearSignature();
    setTrazo(null);
    setNuevoComentario('');
    setSeleccionado(conflicto.actual);
    setConflicto(null);
  };

  /** Toma la versión vigente pero deja la firma y el comentario para revisarlos y confirmar de nuevo. */
  const conservarConflicto = () => {
    if (!conflicto?.actual) return;
    setSeleccionado(conflicto.actual);
    setConflicto(null);
  };

  /** Abandona el convenio y borra lo cargado. */
  const descartarConflicto = () => {
    firmaRef.current?.clearSignature();
    setTrazo(null);
    setNuevoComentario('');
    salirDeSeleccion();
  };

  const abrirPdf = async () => {
    if (!seleccionado?.documento) {
      Alert.alert('Documento no disponible', 'Este convenio no tiene un PDF listo para firmar.');
      return;
    }

    try {
      // El PDF que se abre tiene que corresponder a lo que se va a firmar.
      if (!(await verificarVersion())) return;

      const documento = await descargarDocumentoFirma(
        seleccionado.id_instrumento,
        seleccionado.documento.nombre_archivo,
      );

      if (documento.tipo === 'web') {
        const url = URL.createObjectURL(documento.blob);
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = seleccionado.documento.nombre_archivo;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        return;
      }

      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(documento.uri, {
          mimeType: 'application/pdf',
          dialogTitle: seleccionado.titulo,
        });
      } else {
        Alert.alert('PDF descargado', documento.uri);
      }
    } catch (exception) {
      Alert.alert('No se pudo abrir el PDF', exception instanceof Error ? exception.message : 'Intentá nuevamente.');
    }
  };

  const confirmarFirma = async () => {
    if (!seleccionado) return;

    const conexion = await NetInfo.fetch();
    if (conexion.isConnected !== true || conexion.isInternetReachable === false) {
      Alert.alert(
        'Firma no disponible sin conexión',
        'La evidencia de firma debe enviarse al servidor. Conectá el dispositivo y reintentá.',
      );
      return;
    }

    if (!trazo) {
      Alert.alert('Firma requerida', 'Dibujá tu firma antes de continuar.');
      return;
    }

    setFirmando(true);

    try {
      // Antes de pedir huella y ubicación: si algo cambió, se avisa sin molestar a la persona.
      if (!(await verificarVersion())) return;

      const hardware = await LocalAuthentication.hasHardwareAsync();
      const enrolled = await LocalAuthentication.isEnrolledAsync();
      if (!hardware || !enrolled) {
        throw new Error('El dispositivo no tiene huella o reconocimiento facial configurado.');
      }

      // Solo huella o rostro (sin PIN del celular): se informa a la API como biometría confirmada.
      if (!(await confirmarConBiometria('Confirmá tu identidad para firmar'))) {
        throw new Error('La confirmación biométrica fue cancelada.');
      }

      const { latitud, longitud } = await obtenerUbicacionParaFirma();

      const resultado = await registrarFirma(seleccionado.id_instrumento, {
        trazo,
        latitud,
        longitud,
        biometria_confirmada: true,
        version_registro: seleccionado.version_registro,
      });

      Alert.alert(
        'Firma individual registrada',
        `Tu evidencia quedó guardada. El convenio permanece en estado ${resultado.estado}; Gestión debe registrar por separado la firma administrativa.${resultado.firmas_pendientes > 0 ? ` Quedan ${resultado.firmas_pendientes} firma(s) individual(es) pendiente(s).` : ''}`,
      );

      setTrazo(null);
      seleccionadoRef.current = null;
      setSeleccionado(null);
      await cargar(false);
      if (vinoDeFicha) router.back();
    } catch (exception) {
      // La API responde 409 tanto por una versión vieja como por otros conflictos de estado
      // (p. ej. "Ya firmaste"). Se vuelve a leer el servidor para distinguirlos.
      if (exception instanceof ApiError && exception.status === 409) {
        try {
          if (!(await verificarVersion(exception.message))) return;
        } catch {
          // Sin conexión para releer: se muestra el mensaje original de la API.
        }
      }

      const mensaje = exception instanceof Error ? exception.message : 'Intentá nuevamente.';

      if (exception instanceof UbicacionError && exception.bloqueado) {
        Alert.alert('No se pudo registrar la firma', mensaje, [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Abrir ajustes', onPress: () => void Linking.openSettings() },
        ]);
      } else {
        Alert.alert('No se pudo registrar la firma', mensaje);
      }
    } finally {
      setFirmando(false);
    }
  };

  const publicarComentario = async () => {
    if (!seleccionado || nuevoComentario.trim() === '') return;
    try {
      const comentario = await crearComentarioFirma(seleccionado.id_instrumento, nuevoComentario.trim());
      setComentarios((actuales) => [comentario, ...actuales]);
      setNuevoComentario('');
      if (comentario.ofensivo) Alert.alert('Comentario moderado', 'El texto fue reemplazado por “comentario ofensivo”.');
      else anunciar('Comentario publicado.');
    } catch (exception) {
      Alert.alert('No se pudo publicar', exception instanceof Error ? exception.message : 'Intentá nuevamente.');
    }
  };

  const compartirConstancia = async () => {
    if (!seleccionado) return;
    try {
      const documento = await descargarConstanciaFirma(seleccionado.id_instrumento);
      if (documento.tipo === 'web') {
        const url = URL.createObjectURL(documento.blob);
        const enlace = document.createElement('a');
        enlace.href = url;
        enlace.download = `constancia-firma-${seleccionado.id_instrumento}.pdf`;
        enlace.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
      } else if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(documento.uri, { mimeType: 'application/pdf', dialogTitle: 'Constancia de firma' });
      }
    } catch (exception) {
      Alert.alert('No se pudo generar la constancia', exception instanceof Error ? exception.message : 'Intentá nuevamente.');
    }
  };

  const confirmarDeshabilitado = firmando || !trazo || conflicto !== null;

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Firmador de convenios" />

      <ScrollView
        contentContainerStyle={styles.contenido}
        keyboardShouldPersistTaps="handled"
        refreshControl={
          <RefreshControl
            refreshing={actualizando}
            onRefresh={actualizar}
            tintColor={tema.brand}
            colors={[tema.brand]}
          />
        }>
        {estado === 'cargando' ? (
          <View style={styles.centrado} accessibilityLiveRegion="polite">
            <ActivityIndicator color={tema.brand} />
            <Txt variante="detalle" color="textSecondary">Cargando pendientes…</Txt>
          </View>
        ) : null}

        {estado === 'error' ? (
          <Card titulo="No se pudo cargar el firmador" bordeSuperior={tema.danger}>
            <Txt color="textSecondary" accessibilityRole="alert">{error}</Txt>
            <PressableA11y
              accessibilityRole="button"
              accessibilityHint="Vuelve a pedir los convenios pendientes"
              onPress={() => cargar()}
              style={[styles.boton, { backgroundColor: tema.brand }]}>
              <Txt negrita color="onBrand">Reintentar</Txt>
            </PressableA11y>
          </Card>
        ) : null}

        {estado === 'listo' && pendientes.length === 0 ? (
          <EstadoVacio texto="No tenés convenios pendientes de firma." />
        ) : null}

        {estado === 'listo' && pendientes.length > 0 && !seleccionado ? (
          <View style={styles.lista}>
            {pendientes.map((pendiente) => {
              const detalle = [pendiente.unidad_academica, pendiente.firmantes.map((f) => `${f.nombre} ${f.apellido}`).join(', ')].filter(Boolean).join(' · ');

              return (
                <PressableA11y
                  key={pendiente.id_instrumento}
                  accessibilityRole="button"
                  accessibilityLabel={[pendiente.titulo, detalle, pendiente.demorado ? 'Firma demorada' : null]
                    .filter(Boolean)
                    .join('. ')}
                  accessibilityHint="Abre el convenio para revisarlo y firmarlo"
                  onPress={() => seleccionar(pendiente)}
                  style={({ pressed }) => [
                    styles.item,
                    { borderColor: tema.border, backgroundColor: tema.surface },
                    pendiente.demorado && { borderLeftColor: tema.danger, borderLeftWidth: 4 },
                    pressed && { opacity: 0.75 },
                  ]}>
                  <Txt negrita>{pendiente.titulo}</Txt>
                  <Txt variante="detalle" color="textSecondary">
                    {detalle}
                  </Txt>
                  {pendiente.demorado ? (
                    <Txt variante="etiqueta" style={{ color: tema.danger }}>Firma demorada</Txt>
                  ) : null}
                </PressableA11y>
              );
            })}
          </View>
        ) : null}

        {seleccionado ? (
          <View style={styles.seccion}>
            <PressableA11y
              accessibilityRole="button"
              accessibilityHint={vinoDeFicha ? 'Vuelve a la ficha del convenio' : 'Vuelve a la lista de pendientes'}
              onPress={salirDeSeleccion}
              hitSlop={8}
              style={styles.volver}>
              <Txt variante="detalle" negrita style={{ color: tema.brandText }}>
                {vinoDeFicha ? '‹ Volver al convenio' : '‹ Volver a pendientes'}
              </Txt>
            </PressableA11y>

            {conflicto ? (
              <ConflictoFirmaCard
                conflicto={conflicto}
                hayBorrador={trazo !== null || nuevoComentario.trim() !== ''}
                onRecargar={recargarConflicto}
                onConservar={conservarConflicto}
                onDescartar={descartarConflicto}
              />
            ) : null}

            <Card titulo={seleccionado.titulo} bordeSuperior={tema.brand}>
              <Txt variante="detalle" color="textSecondary">
                {[seleccionado.tipo, seleccionado.unidad_academica].filter(Boolean).join(' · ')}
              </Txt>
              <Txt variante="detalle" color="textSecondary">
                {[seleccionado.expediente ? `Expediente ${seleccionado.expediente}` : null,
                  seleccionado.procedencia ? `Procedencia: ${seleccionado.procedencia}` : null,
                  seleccionado.inicio ? `Inicio: ${seleccionado.inicio.slice(0, 10)}` : null,
                  seleccionado.vencimiento ? `Vence: ${seleccionado.vencimiento.slice(0, 10)}` : null,
                ].filter(Boolean).join(' · ')}
              </Txt>
              {seleccionado.convenio_padre ? (
                <Txt variante="detalle" color="textSecondary">
                  Convenio padre: {seleccionado.convenio_padre.titulo}{seleccionado.convenio_padre.expediente ? ` · ${seleccionado.convenio_padre.expediente}` : ''}
                </Txt>
              ) : null}

              <PressableA11y
                accessibilityRole="button"
                accessibilityHint={
                  seleccionado.documento
                    ? 'Abre el PDF del convenio para leerlo antes de firmar'
                    : 'Este convenio no tiene un PDF disponible'
                }
                accessibilityState={{ disabled: !seleccionado.documento }}
                onPress={abrirPdf}
                disabled={!seleccionado.documento}
                style={[styles.botonSecundario, { borderColor: tema.brand }, !seleccionado.documento && { opacity: 0.5 }]}>
                <Txt negrita style={{ color: tema.brandText }}>Abrir / descargar PDF</Txt>
              </PressableA11y>

              <Txt encabezado variante="subtitulo">Firmantes</Txt>
              {seleccionado.firmantes.map((firmante) => (
                <Txt key={firmante.id_persona} variante="detalle" color="textSecondary">
                  {firmante.nombre} {firmante.apellido}{firmante.firmado ? ' · Firmado' : ' · Pendiente'}
                </Txt>
              ))}

              <Txt encabezado variante="subtitulo">Tu firma</Txt>
              <Txt variante="detalle" color="textSecondary" accessibilityLiveRegion="polite">
                {trazo
                  ? 'Firma guardada. Ya podés confirmar.'
                  : 'Dibujá tu firma con el dedo y tocá "Guardar firma".'}
              </Txt>
              <View
                accessible
                accessibilityLabel="Recuadro de firma. Dibujá tu firma con el dedo."
                style={[styles.firma, { borderColor: tema.border, backgroundColor: '#FFFFFF' }]}>
                <SignatureCanvas
                  ref={firmaRef}
                  onOK={setTrazo}
                  descriptionText=""
                  clearText="Limpiar"
                  confirmText="Guardar firma"
                  penColor="#111111"
                  minWidth={1.5}
                  maxWidth={3}
                  backgroundColor="#FFFFFF"
                  style={{ flex: 1 }}
                />
              </View>

              <View style={styles.botonesFirma}>
                <PressableA11y
                  accessibilityRole="button"
                  accessibilityHint="Borra el dibujo para empezar de nuevo"
                  onPress={() => {
                    firmaRef.current?.clearSignature();
                    setTrazo(null);
                  }}
                  style={[styles.botonSecundario, { borderColor: tema.border }]}>
                  <Txt negrita>Limpiar</Txt>
                </PressableA11y>
                <PressableA11y
                  accessibilityRole="button"
                  accessibilityHint="Guarda el dibujo como tu firma; después se habilita Confirmar firma"
                  onPress={() => firmaRef.current?.readSignature()}
                  style={[styles.botonSecundario, { borderColor: tema.brand }]}>
                  <Txt negrita style={{ color: tema.brandText }}>Guardar firma</Txt>
                </PressableA11y>
              </View>

              <PressableA11y
                accessibilityRole="button"
                accessibilityHint={
                  trazo
                    ? 'Pide tu huella o rostro y registra la firma con tu ubicación'
                    : 'Primero dibujá y guardá tu firma'
                }
                accessibilityState={{ disabled: confirmarDeshabilitado, busy: firmando }}
                onPress={confirmarFirma}
                disabled={confirmarDeshabilitado}
                style={[styles.boton, { backgroundColor: tema.brand }, confirmarDeshabilitado && { opacity: 0.5 }]}
              >
                {firmando ? (
                  <ActivityIndicator color="#FFFFFF" accessibilityLabel="Registrando la firma" />
                ) : (
                  <Txt negrita color="onBrand">Confirmar firma</Txt>
                )}
              </PressableA11y>
            </Card>

            <Card titulo="Comentarios" bordeSuperior={tema.brand}>
              <TextInput
                value={nuevoComentario}
                onChangeText={setNuevoComentario}
                placeholder="Escribí un comentario para los firmantes…"
                placeholderTextColor={tema.textSecondary}
                multiline
                accessibilityLabel="Comentario para los firmantes"
                accessibilityHint="Escribí el texto y después tocá Publicar comentario"
                style={[styles.comentarioInput, {
                  borderColor: tema.border,
                  color: tema.text,
                  backgroundColor: tema.surface,
                  fontSize: 15 * tema.fontScale,
                }]}
              />
              <PressableA11y
                accessibilityRole="button"
                accessibilityHint="Publica el comentario para los demás firmantes"
                accessibilityState={{ disabled: nuevoComentario.trim() === '' }}
                onPress={publicarComentario}
                disabled={nuevoComentario.trim() === ''}
                style={[styles.botonSecundario, { borderColor: tema.brand }, nuevoComentario.trim() === '' && { opacity: 0.5 }]}
              >
                <Txt negrita style={{ color: tema.brandText }}>Publicar comentario</Txt>
              </PressableA11y>

              {comentarios.length === 0 ? (
                <Txt variante="detalle" color="textSecondary">No hay comentarios aún.</Txt>
              ) : null}

              {comentarios.map((comentario) => (
                <View
                  key={comentario.id_comentario}
                  accessible
                  accessibilityLabel={`${comentario.usuario.nombre}. ${comentario.contenido}. ${new Date(comentario.fecha).toLocaleString()}`}
                  style={[styles.comentario, { borderColor: tema.border }]}>
                  <Txt negrita>{comentario.usuario.nombre}</Txt>
                  <Txt variante="detalle" color={comentario.ofensivo ? 'textSecondary' : 'text'}>
                    {comentario.contenido}
                  </Txt>
                  <Txt variante="etiqueta" color="textSecondary">
                    {new Date(comentario.fecha).toLocaleString()}
                  </Txt>
                </View>
              ))}
            </Card>

            <Card titulo="Acciones" bordeSuperior={tema.brand}>
              <View style={styles.botonesAcciones}>
                <PressableA11y
                  accessibilityRole="button"
                  accessibilityHint="Genera la constancia de firma en PDF y abre el menú para compartirla"
                  onPress={compartirConstancia}
                  style={[styles.botonAccion, { borderColor: tema.brand }]}
                >
                  <Txt negrita style={{ color: tema.brandText }}>Compartir constancia</Txt>
                </PressableA11y>
              </View>
            </Card>
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  contenido: { padding: SgcSpacing.md, gap: SgcSpacing.md, paddingBottom: SgcSpacing.xl },
  centrado: { alignItems: 'center', gap: SgcSpacing.sm, paddingVertical: SgcSpacing.xl },
  lista: { gap: SgcSpacing.sm },
  item: { borderWidth: 1, borderRadius: SgcRadius.md, padding: SgcSpacing.md, gap: SgcSpacing.sm },
  seccion: { gap: SgcSpacing.md },
  volver: { minHeight: AREA_TACTIL_MINIMA, justifyContent: 'center' },
  firma: { height: 220, borderWidth: 1, borderRadius: SgcRadius.md, overflow: 'hidden' },
  botonesFirma: { flexDirection: 'row', gap: SgcSpacing.sm },
  boton: { borderRadius: SgcRadius.sm, paddingVertical: SgcSpacing.md, alignItems: 'center' },
  botonSecundario: { borderWidth: 1, borderRadius: SgcRadius.sm, paddingVertical: SgcSpacing.md, alignItems: 'center', flex: 1 },
  comentarios: { gap: SgcSpacing.sm, maxHeight: 200, overflow: 'hidden' },
  comentario: { borderWidth: 1, borderRadius: SgcRadius.md, padding: SgcSpacing.md, gap: SgcSpacing.sm },
  nuevoComentario: { flexDirection: 'row', gap: SgcSpacing.sm, alignItems: 'center' },
  botonesAcciones: { flexDirection: 'row', gap: SgcSpacing.sm },
  botonAccion: { borderWidth: 1, borderRadius: SgcRadius.sm, paddingVertical: SgcSpacing.md, alignItems: 'center', flex: 1 },
  comentarioInput: { borderWidth: 1, borderRadius: SgcRadius.md, padding: SgcSpacing.md, maxHeight: 100, textAlignVertical: 'top' },
});
