import { CameraView, useCameraPermissions } from 'expo-camera';
import { useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Linking, Pressable, StyleSheet, View } from 'react-native';

import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { Txt } from '@/components/panel/txt';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';
import { parsearQrConvenio } from '@/lib/qr';
import { ApiError, NetworkError } from '@/services/api-client';
import { clearToken } from '@/lib/auth-storage';
import { obtenerConvenio } from '@/services/convenios';
import { Destinos } from '@/constants/navigation';

const MENSAJES_QR = {
  formato: 'Este código QR no corresponde a un convenio. Buscá el código impreso en el documento.',
  origen: 'El código pertenece a otro sistema, no a esta aplicación.',
} as const;

export default function EscanearQrScreen() {
  const tema = useSgcTheme();
  const router = useRouter();
  const [permission, requestPermission] = useCameraPermissions();
  const [procesando, setProcesando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [escaneado, setEscaneado] = useState(false);

  const procesar = useCallback(
    async (contenido: string) => {
      if (escaneado || procesando) return;

      const resultado = parsearQrConvenio(contenido);
      if (!resultado.ok) {
        setError(MENSAJES_QR[resultado.motivo]);
        return;
      }

      setEscaneado(true);
      setProcesando(true);
      setError(null);

      try {
        await obtenerConvenio(resultado.id);
        router.replace(Destinos.convenio(resultado.id));
      } catch (causa) {
        if (causa instanceof ApiError && causa.status === 404) {
          setError('No se encontró el convenio o no tenés permiso para verlo.');
          setEscaneado(false);
        } else if (causa instanceof ApiError && causa.status === 401) {
          // Sesión expirada: se vuelve al inicio de sesión, igual que en el panel.
          await clearToken();
          router.replace('/');
        } else if (causa instanceof NetworkError) {
          setError('Sin conexión: no se pudo validar el código. Conectate y volvé a intentar.');
          setEscaneado(false);
        } else {
          setError('No se pudo abrir el convenio. Volvé a intentar en unos segundos.');
          setEscaneado(false);
        }
      } finally {
        setProcesando(false);
      }
    },
    [escaneado, procesando, router],
  );

  if (!permission) {
    return (
      <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
        <EncabezadoPantalla titulo="Escanear código QR" />
        <View style={styles.centrado}><ActivityIndicator color={tema.brand} /></View>
      </View>
    );
  }

  if (!permission.granted) {
    // Permiso denegado permanentemente: el diálogo del sistema ya no aparece.
    const puedeVolverAPedir = permission.canAskAgain;

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
            onPress={puedeVolverAPedir ? requestPermission : () => void Linking.openSettings()}
            style={[styles.boton, { backgroundColor: tema.brand }]}>
            <Txt negrita color="onBrand">
              {puedeVolverAPedir ? 'Permitir cámara' : 'Abrir ajustes'}
            </Txt>
          </Pressable>
          {!puedeVolverAPedir ? (
            <Txt variante="detalle" color="textSecondary" style={styles.centradoTexto}>
              Activá el permiso de cámara desde la configuración del teléfono.
            </Txt>
          ) : null}
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
