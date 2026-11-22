import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  BackHandler,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  View,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import * as LocalAuthentication from 'expo-local-authentication';
import * as Location from 'expo-location';
import * as Sharing from 'expo-sharing';
import SignatureCanvas, {
  type SignatureViewRef,
} from 'react-native-signature-canvas';

import { Card, EstadoVacio } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { descargarDocumentoFirma, listarPendientesFirma, registrarFirma } from '@/services/firma';
import type { PendienteFirmaCompleto } from '@/types/firma';

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

  const cargar = useCallback(async (mostrarCarga = true) => {
    if (mostrarCarga) setEstado('cargando');
    setError(null);

    try {
      const lista = await listarPendientesFirma();
      setPendientes(lista);
      setSeleccionado((actual) =>
        actual ? lista.find((item) => item.id_instrumento === actual.id_instrumento) ?? null : null,
      );
      setEstado('listo');
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'No se pudieron cargar los pendientes.');
      setEstado('error');
    }
  }, []);

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
    setSeleccionado(pendiente);
    setTrazo(null);
  };

  const abrirPdf = async () => {
    if (!seleccionado?.documento) {
      Alert.alert('Documento no disponible', 'Este convenio no tiene un PDF listo para firmar.');
      return;
    }

    try {
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
    if (!trazo) {
      Alert.alert('Firma requerida', 'Dibujá tu firma antes de continuar.');
      return;
    }

    setFirmando(true);

    try {
      const hardware = await LocalAuthentication.hasHardwareAsync();
      const enrolled = await LocalAuthentication.isEnrolledAsync();
      if (!hardware || !enrolled) {
        throw new Error('El dispositivo no tiene huella o reconocimiento facial configurado.');
      }

      const autenticacion = await LocalAuthentication.authenticateAsync({
        promptMessage: 'Confirmá tu identidad para firmar',
        cancelLabel: 'Cancelar',
        disableDeviceFallback: false,
      });
      if (!autenticacion.success) throw new Error('La confirmación biométrica fue cancelada.');

      const permiso = await Location.requestForegroundPermissionsAsync();
      if (permiso.status !== 'granted') {
        throw new Error('Hace falta permiso de ubicación para registrar la firma.');
      }

      const posicion = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });

      const resultado = await registrarFirma(seleccionado.id_instrumento, {
        trazo,
        latitud: posicion.coords.latitude,
        longitud: posicion.coords.longitude,
        biometria_confirmada: true,
      });

      Alert.alert(
        'Firma individual registrada',
        `Tu evidencia quedó guardada. El convenio permanece en estado ${resultado.estado}; Gestión debe registrar por separado la firma administrativa.${resultado.firmas_pendientes > 0 ? ` Quedan ${resultado.firmas_pendientes} firma(s) individual(es) pendiente(s).` : ''}`,
      );

      setTrazo(null);
      setSeleccionado(null);
      await cargar(false);
      if (vinoDeFicha) router.back();
    } catch (exception) {
      Alert.alert('No se pudo registrar la firma', exception instanceof Error ? exception.message : 'Intentá nuevamente.');
    } finally {
      setFirmando(false);
    }
  };

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Firmador de convenios" />

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
        {estado === 'cargando' ? (
          <View style={styles.centrado}>
            <ActivityIndicator color={tema.brand} />
            <Txt variante="detalle" color="textSecondary">Cargando pendientes…</Txt>
          </View>
        ) : null}

        {estado === 'error' ? (
          <Card titulo="No se pudo cargar el firmador" bordeSuperior={tema.danger}>
            <Txt color="textSecondary">{error}</Txt>
            <Pressable accessibilityRole="button" onPress={() => cargar()} style={[styles.boton, { backgroundColor: tema.brand }]}>
              <Txt negrita color="onBrand">Reintentar</Txt>
            </Pressable>
          </Card>
        ) : null}

        {estado === 'listo' && pendientes.length === 0 ? (
          <EstadoVacio texto="No tenés convenios pendientes de firma." />
        ) : null}

        {estado === 'listo' && pendientes.length > 0 && !seleccionado ? (
          <View style={styles.lista}>
            {pendientes.map((pendiente) => (
              <Pressable
                key={pendiente.id_instrumento}
                accessibilityRole="button"
                onPress={() => seleccionar(pendiente)}
                style={({ pressed }) => [
                  styles.item,
                  { borderColor: tema.border, backgroundColor: tema.surface },
                  pendiente.demorado && { borderLeftColor: tema.danger, borderLeftWidth: 4 },
                  pressed && { opacity: 0.75 },
                ]}>
                <Txt negrita>{pendiente.titulo}</Txt>
                <Txt variante="detalle" color="textSecondary">
                  {[pendiente.unidad_academica, pendiente.firmantes.map((f) => `${f.nombre} ${f.apellido}`).join(', ')].filter(Boolean).join(' · ')}
                </Txt>
                {pendiente.demorado ? (
                  <Txt variante="etiqueta" style={{ color: tema.danger }}>Firma demorada</Txt>
                ) : null}
              </Pressable>
            ))}
          </View>
        ) : null}

        {seleccionado ? (
          <View style={styles.seccion}>
            <Pressable accessibilityRole="button" onPress={salirDeSeleccion} hitSlop={8}>
              <Txt variante="detalle" negrita style={{ color: tema.brandText }}>
                {vinoDeFicha ? '‹ Volver al convenio' : '‹ Volver a pendientes'}
              </Txt>
            </Pressable>

            <Card titulo={seleccionado.titulo} bordeSuperior={tema.brand}>
              <Txt variante="detalle" color="textSecondary">
                {[seleccionado.tipo, seleccionado.unidad_academica].filter(Boolean).join(' · ')}
              </Txt>

              <Pressable
                accessibilityRole="button"
                onPress={abrirPdf}
                disabled={!seleccionado.documento}
                style={[styles.botonSecundario, { borderColor: tema.brand }, !seleccionado.documento && { opacity: 0.5 }]}>
                <Txt negrita style={{ color: tema.brandText }}>Abrir / descargar PDF</Txt>
              </Pressable>

              <Txt variante="subtitulo">Firmantes</Txt>
              {seleccionado.firmantes.map((firmante) => (
                <Txt key={firmante.id_persona} variante="detalle" color="textSecondary">
                  {firmante.nombre} {firmante.apellido}{firmante.firmado ? ' · Firmado' : ' · Pendiente'}
                </Txt>
              ))}

              <Txt variante="subtitulo">Tu firma</Txt>
              <View style={[styles.firma, { borderColor: tema.border, backgroundColor: '#FFFFFF' }]}>
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
                <Pressable
                  accessibilityRole="button"
                  onPress={() => firmaRef.current?.clearSignature()}
                  style={[styles.botonSecundario, { borderColor: tema.border }]}>
                  <Txt negrita>Limpiar</Txt>
                </Pressable>
                <Pressable
                  accessibilityRole="button"
                  onPress={() => firmaRef.current?.readSignature()}
                  style={[styles.botonSecundario, { borderColor: tema.brand }]}>
                  <Txt negrita style={{ color: tema.brandText }}>Guardar firma</Txt>
                </Pressable>
              </View>

              <Pressable
                accessibilityRole="button"
                onPress={confirmarFirma}
                disabled={firmando || !trazo}
                style={[styles.boton, { backgroundColor: tema.brand }, (firmando || !trazo) && { opacity: 0.5 }]}
              >
                {firmando ? (
                  <ActivityIndicator color="#FFFFFF" />
                ) : (
                  <Txt negrita color="onBrand">Confirmar firma</Txt>
                )}
              </Pressable>
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
  firma: { height: 220, borderWidth: 1, borderRadius: SgcRadius.md, overflow: 'hidden' },
  botonesFirma: { flexDirection: 'row', gap: SgcSpacing.sm },
  boton: { borderRadius: SgcRadius.sm, paddingVertical: SgcSpacing.md, alignItems: 'center' },
  botonSecundario: { borderWidth: 1, borderRadius: SgcRadius.sm, paddingVertical: SgcSpacing.md, alignItems: 'center', flex: 1 },
});
