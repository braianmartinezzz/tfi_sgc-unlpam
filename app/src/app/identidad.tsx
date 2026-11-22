import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Image } from 'expo-image';
import SignatureCanvas, { type SignatureViewRef } from 'react-native-signature-canvas';

import { Card } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { Txt } from '@/components/panel/txt';
import { SgcRadius, SgcSpacing } from '@/constants/sgc-theme';
import { usePanel } from '@/hooks/use-panel';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { verificarBiometria } from '@/lib/biometria';
import {
  borrarBiometriaRegistrada,
  borrarFirmaRegistrada,
  guardarBiometriaRegistrada,
  guardarFirmaRegistrada,
  leerIdentidad,
} from '@/lib/identity-storage';
import {
  IDENTIDAD_VACIA,
  type CampoBiometrico,
  type IdentidadRegistrada,
} from '@/types/identidad';

type Ocupado = 'firma' | CampoBiometrico | null;

const mensajeDe = (error: unknown) =>
  error instanceof Error ? error.message : 'Intentá nuevamente.';

const fechaLegible = (iso: string) =>
  new Date(iso).toLocaleString('es-AR', { dateStyle: 'long', timeStyle: 'short' });

export default function IdentidadScreen() {
  const tema = useSgcTheme();
  const { me, estado: estadoPanel } = usePanel();
  const idUsuario = me?.id_usuario ?? null;
  const firmaRef = useRef<SignatureViewRef>(null);

  const [identidad, setIdentidad] = useState<IdentidadRegistrada>(IDENTIDAD_VACIA);
  const [cargando, setCargando] = useState(true);
  const [errorCarga, setErrorCarga] = useState<string | null>(null);
  const [dibujando, setDibujando] = useState(false);
  const [scrollActivo, setScrollActivo] = useState(true);
  const [ocupado, setOcupado] = useState<Ocupado>(null);

  useEffect(() => {
    if (idUsuario === null) return;
    let activo = true;

    void (async () => {
      try {
        const datos = await leerIdentidad(idUsuario);
        if (activo) setIdentidad(datos);
      } catch (error) {
        if (activo) setErrorCarga(mensajeDe(error));
      } finally {
        if (activo) setCargando(false);
      }
    })();

    return () => {
      activo = false;
    };
  }, [idUsuario]);

  const guardarFirma = async (imagen: string) => {
    if (idUsuario === null) return;
    setOcupado('firma');

    try {
      const firma = await guardarFirmaRegistrada(idUsuario, imagen);
      setIdentidad((actual) => ({ ...actual, firma }));
      setDibujando(false);
    } catch (error) {
      Alert.alert('No se pudo guardar la firma', mensajeDe(error));
    } finally {
      setOcupado(null);
    }
  };

  const registrarBiometria = async (campo: CampoBiometrico) => {
    if (idUsuario === null) return;
    setOcupado(campo);

    try {
      await verificarBiometria(campo);
      const registro = await guardarBiometriaRegistrada(idUsuario, campo);
      setIdentidad((actual) => ({ ...actual, [campo]: registro }));
    } catch (error) {
      Alert.alert('No se pudo registrar', mensajeDe(error));
    } finally {
      setOcupado(null);
    }
  };

  const eliminar = (campo: 'firma' | CampoBiometrico) => {
    if (idUsuario === null) return;
    const nombre =
      campo === 'firma' ? 'la firma' : campo === 'rostro' ? 'el reconocimiento facial' : 'la huella digital';

    Alert.alert('Quitar registro', `¿Querés quitar ${nombre} registrada en este dispositivo?`, [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Quitar',
        style: 'destructive',
        onPress: async () => {
          try {
            if (campo === 'firma') await borrarFirmaRegistrada(idUsuario);
            else await borrarBiometriaRegistrada(idUsuario, campo);
            setIdentidad((actual) => ({ ...actual, [campo]: null }));
          } catch (error) {
            Alert.alert('No se pudo quitar el registro', mensajeDe(error));
          }
        },
      },
    ]);
  };

  const esperandoUsuario = idUsuario === null && estadoPanel === 'cargando';
  const sinUsuario = idUsuario === null && estadoPanel !== 'cargando';

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Mi identidad" />

      <ScrollView contentContainerStyle={styles.contenido} scrollEnabled={scrollActivo}>
        <View style={styles.columna}>
          <Txt variante="detalle" color="textSecondary">
            Tu identidad queda guardada de forma segura solo en este dispositivo.
          </Txt>

          {esperandoUsuario || (idUsuario !== null && cargando) ? (
            <View style={styles.centrado}>
              <ActivityIndicator color={tema.brand} />
            </View>
          ) : null}

          {sinUsuario ? (
            <Card titulo="No se pudo cargar tu usuario" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">Revisá tu conexión y volvé a abrir esta pantalla.</Txt>
            </Card>
          ) : null}

          {errorCarga ? (
            <Card titulo="No se pudo leer tu identidad" bordeSuperior={tema.danger}>
              <Txt color="textSecondary">{errorCarga}</Txt>
            </Card>
          ) : null}

          {idUsuario !== null && !cargando ? (
            <>
              <Card
                titulo="Firma"
                bordeSuperior={identidad.firma ? tema.success : tema.border}
                acento={<Estado registrado={!!identidad.firma} />}>
                {identidad.firma && !dibujando ? (
                  <>
                    <View style={[styles.firma, { borderColor: tema.border, backgroundColor: '#FFFFFF' }]}>
                      <Image
                        source={{ uri: identidad.firma.imagen }}
                        contentFit="contain"
                        style={styles.imagenFirma}
                        accessibilityLabel="Tu firma registrada"
                      />
                    </View>
                    <Txt variante="detalle" color="textSecondary">
                      Registrada el {fechaLegible(identidad.firma.fecha)}
                    </Txt>
                    <View style={styles.fila}>
                      <Boton texto="Volver a firmar" onPress={() => setDibujando(true)} />
                      <Boton texto="Quitar" peligro onPress={() => eliminar('firma')} />
                    </View>
                  </>
                ) : null}

                {!identidad.firma && !dibujando ? (
                  <>
                    <Txt color="textSecondary">Todavía no registraste tu firma.</Txt>
                    <Boton texto="Registrar firma" principal onPress={() => setDibujando(true)} />
                  </>
                ) : null}

                {dibujando ? (
                  <>
                    <Txt variante="detalle" color="textSecondary">
                      Dibujá tu firma dentro del cuadro.
                    </Txt>
                    <View style={[styles.firma, { borderColor: tema.border, backgroundColor: '#FFFFFF' }]}>
                      <SignatureCanvas
                        ref={firmaRef}
                        onOK={guardarFirma}
                        onEmpty={() => Alert.alert('Firma requerida', 'Dibujá tu firma antes de guardar.')}
                        onBegin={() => setScrollActivo(false)}
                        onEnd={() => setScrollActivo(true)}
                        descriptionText=""
                        webStyle=".m-signature-pad--footer {display: none; margin: 0px;}"
                        penColor="#111111"
                        minWidth={1.5}
                        maxWidth={3}
                        backgroundColor="#FFFFFF"
                        trimWhitespace
                        style={{ flex: 1 }}
                      />
                    </View>
                    <View style={styles.fila}>
                      <Boton texto="Limpiar" onPress={() => firmaRef.current?.clearSignature()} />
                      <Boton
                        texto="Cancelar"
                        onPress={() => {
                          setDibujando(false);
                          setScrollActivo(true);
                        }}
                      />
                    </View>
                    <Boton
                      texto="Guardar firma"
                      principal
                      cargando={ocupado === 'firma'}
                      onPress={() => firmaRef.current?.readSignature()}
                    />
                  </>
                ) : null}
              </Card>

              <CardBiometrica
                titulo="Reconocimiento facial"
                campo="rostro"
                registro={identidad.rostro}
                ocupado={ocupado === 'rostro'}
                onRegistrar={() => registrarBiometria('rostro')}
                onQuitar={() => eliminar('rostro')}
              />

              <CardBiometrica
                titulo="Huella digital"
                campo="huella"
                registro={identidad.huella}
                ocupado={ocupado === 'huella'}
                onRegistrar={() => registrarBiometria('huella')}
                onQuitar={() => eliminar('huella')}
              />

              <Txt variante="detalle" color="textSecondary">
                Por seguridad, el sistema del teléfono no entrega tu huella ni tu rostro a la app: solo confirma que
                sos vos. Si el dispositivo tiene ambos métodos, puede aceptar cualquiera de los dos.
              </Txt>
            </>
          ) : null}
        </View>
      </ScrollView>
    </View>
  );
}

function CardBiometrica({
  titulo,
  campo,
  registro,
  ocupado,
  onRegistrar,
  onQuitar,
}: {
  titulo: string;
  campo: CampoBiometrico;
  registro: { fecha: string } | null;
  ocupado: boolean;
  onRegistrar: () => void;
  onQuitar: () => void;
}) {
  const tema = useSgcTheme();

  return (
    <Card
      titulo={titulo}
      bordeSuperior={registro ? tema.success : tema.border}
      acento={<Estado registrado={!!registro} />}>
      {registro ? (
        <>
          <Txt variante="detalle" color="textSecondary">
            Verificada el {fechaLegible(registro.fecha)}
          </Txt>
          <View style={styles.fila}>
            <Boton texto="Verificar de nuevo" cargando={ocupado} onPress={onRegistrar} />
            <Boton texto="Quitar" peligro onPress={onQuitar} />
          </View>
        </>
      ) : (
        <>
          <Txt color="textSecondary">
            {campo === 'rostro'
              ? 'Todavía no verificaste tu rostro en este dispositivo.'
              : 'Todavía no verificaste tu huella en este dispositivo.'}
          </Txt>
          <Boton texto="Registrar" principal cargando={ocupado} onPress={onRegistrar} />
        </>
      )}
    </Card>
  );
}

function Estado({ registrado }: { registrado: boolean }) {
  const tema = useSgcTheme();

  return (
    <Txt variante="etiqueta" style={{ color: registrado ? tema.success : tema.textSecondary }}>
      {registrado ? 'Registrada' : 'Sin registrar'}
    </Txt>
  );
}

function Boton({
  texto,
  onPress,
  principal,
  peligro,
  cargando,
}: {
  texto: string;
  onPress: () => void;
  principal?: boolean;
  peligro?: boolean;
  cargando?: boolean;
}) {
  const tema = useSgcTheme();
  const colorBorde = peligro ? tema.danger : principal ? tema.brand : tema.border;

  return (
    <Pressable
      accessibilityRole="button"
      disabled={cargando}
      onPress={onPress}
      style={({ pressed }) => [
        styles.boton,
        { borderColor: colorBorde, backgroundColor: principal ? tema.brand : 'transparent' },
        (pressed || cargando) && { opacity: 0.6 },
      ]}>
      {cargando ? (
        <ActivityIndicator color={principal ? tema.onBrand : tema.brand} />
      ) : (
        <Txt negrita style={{ color: principal ? tema.onBrand : peligro ? tema.danger : tema.text }}>
          {texto}
        </Txt>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  contenido: { padding: SgcSpacing.lg, paddingBottom: SgcSpacing.xl * 2 },
  columna: { width: '100%', maxWidth: 800, alignSelf: 'center', gap: SgcSpacing.lg },
  centrado: { alignItems: 'center', padding: SgcSpacing.xl },
  firma: { height: 220, borderWidth: 1, borderRadius: SgcRadius.md, overflow: 'hidden' },
  imagenFirma: { flex: 1 },
  fila: { flexDirection: 'row', gap: SgcSpacing.sm },
  boton: {
    flex: 1,
    borderWidth: 1,
    borderRadius: SgcRadius.sm,
    paddingVertical: SgcSpacing.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
