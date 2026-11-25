import { CameraView, useCameraPermissions } from 'expo-camera';
import { useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, View } from 'react-native';

import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { Txt } from '@/components/panel/txt';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { obtenerConvenio } from '@/services/convenios';
import { Destinos } from '@/constants/navigation';

function extraerIdConvenio(contenido: string): number | null {
  const texto = contenido.trim();

  if (/^\d+$/.test(texto)) {
    const id = Number(texto);
    return Number.isInteger(id) && id > 0 ? id : null;
  }

  const prefijo = texto.match(/^sgc:convenio:(\d+)$/i);
  if (prefijo) return Number(prefijo[1]);

  try {
    const url = new URL(texto);
    const coincidencia = url.pathname.match(/(?:convenios|convenio)\/(\d+)/i);
    return coincidencia ? Number(coincidencia[1]) : null;
  } catch {
    return null;
  }
}

export default function EscanearQrScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const [permission, requestPermission] = useCameraPermissions();
  const [procesando, setProcesando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [escaneado, setEscaneado] = useState(false);

  const procesar = useCallback(async (contenido: string) => {
    if (escaneado || procesando) return;

    const id = extraerIdConvenio(contenido);
    if (!id) {
      setError('El código no corresponde a un convenio válido.');
      return;
    }

    setEscaneado(true);
    setProcesando(true);
    setError(null);

    try {
      await obtenerConvenio(id);
      router.replace(Destinos.convenio(id));
    } catch {
      setError('No se encontró el convenio o no tenés permiso para verlo.');
      setEscaneado(false);
    } finally {
      setProcesando(false);
    }
  }, [escaneado, procesando, router]);

  if (!permission) {
    return (
      <View style={[styles.pantalla, { backgroundColor: tema.page }]}> 
        <EncabezadoPantalla titulo="Escanear código QR" />
        <View style={styles.centrado}><ActivityIndicator color={tema.brand} /></View>
      </View>
    );
  }

  if (!permission.granted) {
    return (
      <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
        <EncabezadoPantalla titulo="Escanear código QR" />
        <View style={styles.centrado}>
          <Txt variante="subtitulo" style={styles.centradoTexto}>Se necesita acceso a la cámara</Txt>
          <Txt variante="detalle" color="textSecondary" style={styles.centradoTexto}>
            Usamos la cámara únicamente para leer el identificador de un convenio.
          </Txt>
          <Pressable
            accessibilityRole="button"
            onPress={requestPermission}
            style={[styles.boton, { backgroundColor: tema.brand }]}>
            <Txt negrita color="onBrand">Permitir cámara</Txt>
          </Pressable>
        </View>
      </View>
    );
  }

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Escanear código QR" />
      <CameraView
        style={styles.camara}
        facing="back"
        barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
        onBarcodeScanned={({ data }) => void procesar(data)}
      />
      <View style={[styles.inferior, { backgroundColor: tema.page }]}>
        {procesando ? <ActivityIndicator color={tema.brand} /> : null}
        {error ? (
          <Txt variante="detalle" style={[styles.error, { color: tema.danger }]}>
            {error}
          </Txt>
        ) : (
          <Txt variante="detalle" color="textSecondary" style={styles.centradoTexto}>
            Apuntá la cámara al código QR del convenio.
          </Txt>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  camara: { flex: 1, margin: SgcSpacing.lg, borderRadius: 12, overflow: 'hidden' },
  inferior: { paddingHorizontal: SgcSpacing.lg, paddingBottom: SgcSpacing.lg, gap: SgcSpacing.sm },
  centrado: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SgcSpacing.xl, gap: SgcSpacing.md },
  centradoTexto: { textAlign: 'center' },
  boton: { minHeight: 48, minWidth: 180, borderRadius: 8, alignItems: 'center', justifyContent: 'center', paddingHorizontal: SgcSpacing.lg },
  error: { textAlign: 'center', fontWeight: '600' },
});
