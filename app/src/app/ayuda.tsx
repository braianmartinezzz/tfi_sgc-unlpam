import { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { Card } from '@/components/panel/card';
import { EncabezadoPantalla } from '@/components/panel/encabezado-pantalla';
import { Txt } from '@/components/panel/txt';
import { SgcSpacing } from '@/constants/sgc-theme';
import { useSgcTheme } from '@/hooks/use-sgc-theme';

type Tema = { clave: string; titulo: string; pasos: string[] };

const TEMAS: Tema[] = [
  {
    clave: 'sesion',
    titulo: 'Iniciar y cerrar sesión',
    pasos: [
      'Ingresá con tu correo institucional y tu contraseña.',
      'La sesión queda guardada en el dispositivo: no hace falta iniciarla de nuevo cada vez.',
      'Si la sesión vence, la aplicación te lleva sola a la pantalla de ingreso.',
      'Para salir, abrí Mi perfil y tocá "Cerrar sesión".',
    ],
  },
  {
    clave: 'panel',
    titulo: 'El panel',
    pasos: [
      'Muestra tus convenios pendientes de firma, los totales por estado y los últimos movimientos.',
      'Los avisos señalan preavisos de vencimiento y firmas demoradas; tocá uno para abrir el convenio.',
      'Deslizá hacia abajo para actualizar los datos.',
    ],
  },
  {
    clave: 'buscar',
    titulo: 'Buscar convenios',
    pasos: [
      'Escribí parte del título en el buscador, desde el panel o desde la lista de convenios.',
      'Con "Filtros" podés acotar por estado, tipo (marco o específico) y, según tu rol, por facultad.',
      'Los estados que se ofrecen dependen de tu rol.',
      'Tocá "Limpiar filtros" para volver a ver todo.',
    ],
  },
  {
    clave: 'ficha',
    titulo: 'Ficha de un convenio',
    pasos: [
      'Tocá un convenio de cualquier lista para ver su estado, tipo, unidad académica, vencimiento e instituciones firmantes.',
      'Si el convenio espera tu firma, vas a ver el botón "Ir a firmar".',
    ],
  },
  {
    clave: 'firma',
    titulo: 'Firmar un convenio',
    pasos: [
      'Solo pueden firmar los roles habilitados; para el resto el firmador no aparece.',
      'Elegí el convenio y, si querés, abrí el PDF para leerlo antes de firmar.',
      'Dibujá tu firma y tocá "Guardar firma".',
      'Al confirmar, la aplicación te pide tu huella o reconocimiento facial y permiso para registrar la ubicación.',
      'Cuando firman todas las partes, el convenio pasa a "Firmado".',
    ],
  },
  {
    clave: 'offline',
    titulo: 'Sin conexión',
    pasos: [
      'Sin internet podés ver los pendientes de firma guardados en el dispositivo.',
      'La búsqueda, las notificaciones y las firmas necesitan conexión.',
      'Al recuperarla, el panel se actualiza solo.',
    ],
  },
];

export default function AyudaScreen() {
  const tema = useSgcTheme();
  const [abierto, setAbierto] = useState<string | null>(TEMAS[0].clave);

  return (
    <View style={[styles.pantalla, { backgroundColor: tema.page }]}>
      <EncabezadoPantalla titulo="Ayuda y manual de usuario" />

      <ScrollView contentContainerStyle={styles.contenido}>
        <View style={styles.columna}>
          <Txt variante="detalle" color="textSecondary">
            Guía rápida del Sistema de Gestión de Convenios (SGC) de la UNLPam.
          </Txt>

          {TEMAS.map((tematica) => {
            const expandido = abierto === tematica.clave;

            return (
              <Card key={tematica.clave} bordeSuperior={expandido ? tema.brand : undefined}>
                <Pressable
                  accessibilityRole="button"
                  accessibilityState={{ expanded: expandido }}
                  onPress={() => setAbierto(expandido ? null : tematica.clave)}
                  style={styles.titulo}>
                  <Txt variante="subtitulo" style={styles.tituloTexto}>
                    {tematica.titulo}
                  </Txt>
                  <Txt variante="subtitulo" color="brandText">
                    {expandido ? '−' : '+'}
                  </Txt>
                </Pressable>

                {expandido
                  ? tematica.pasos.map((paso) => (
                      <View key={paso} style={styles.paso}>
                        <Txt color="textSecondary">•</Txt>
                        <Txt style={styles.pasoTexto}>{paso}</Txt>
                      </View>
                    ))
                  : null}
              </Card>
            );
          })}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  pantalla: { flex: 1 },
  contenido: { padding: SgcSpacing.lg, paddingBottom: SgcSpacing.xl * 2 },
  columna: { width: '100%', maxWidth: 800, alignSelf: 'center', gap: SgcSpacing.md },
  titulo: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: SgcSpacing.sm },
  tituloTexto: { flex: 1 },
  paso: { flexDirection: 'row', gap: SgcSpacing.sm },
  pasoTexto: { flex: 1 },
});
