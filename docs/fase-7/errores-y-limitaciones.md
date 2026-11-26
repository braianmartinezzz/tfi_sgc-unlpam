# Fase 7 — Registro de errores y limitaciones conocidas

Estado al 25/11/2026, rama `braian`. Se arma leyendo el código de la app y la documentación de la Fase 6; **nada de lo que dice "pendiente de probar" se verificó en un celular**. Gravedad: **1** molestia, **2** demora o confusión, **3** impide terminar la tarea.

## A. Errores encontrados y corregidos en la Fase 7

| ID | Error | Gravedad | Dónde | Corrección |
|---|---|---|---|---|
| E-01 | "Agregar foto de perfil" tenía texto blanco sobre fondo blanco: el botón no se veía en modo claro. | 3 | `perfil.tsx` | Fondo institucional. |
| E-02 | El rojo de error y de "firma demorada" no llegaba a 4,5:1 sobre el gris de página (4,21) ni sobre filas alternadas (4,11). | 2 | `sgc-theme.ts` | `danger` claro `#b9302e`. |
| E-03 | El error del login usaba un rojo fijo: 3,52:1 en modo oscuro. | 2 | `index.tsx`, `verificacion-mfa.tsx` | Usa el color del tema. |
| E-04 | Placeholder del buscador en modo oscuro: 4,37:1. | 1 | `sgc-theme.ts` | `#9a9da9` (5,08). |
| E-05 | "Limpiar" en el firmador borraba el dibujo pero dejaba habilitado "Confirmar firma" con el trazo anterior. | 3 | `firmador.tsx` | Limpiar también invalida la firma guardada. |
| E-06 | El tamaño de letra elegido en Mi perfil no se aplicaba a textos con tamaño propio (login, verificación). | 2 | `txt.tsx` | El multiplicador se aplica siempre. |
| E-07 | Una notificación sin leer se distinguía solo por color y negrita. | 2 | `notificaciones*.tsx` | Se escribe "Nueva" y el lector dice "Sin leer". |
| E-08 | Las cajas de resumen del panel no leían su nota ("N vencido(s) sin archivar"). | 2 | `stat-box.tsx` | La nota entra en la etiqueta. |
| E-09 | Una notificación sin convenio asociado anunciaba "Marca la notificación como leída" pero estaba deshabilitada. | 1 | `notificaciones.tsx` | Sin hint cuando no es accionable. |
| E-10 | Guardar la firma no daba ninguna respuesta visible ni sonora. | 2 | `firmador.tsx` | Texto de estado y anuncio. |

## B. Limitaciones conocidas (abiertas)

| ID | Limitación | Gravedad | Alternativa o decisión |
|---|---|---|---|
| L-01 | El recuadro de firma es un WebView (`react-native-signature-canvas`). Con lector de pantalla hay que usar el gesto de pase directo (doble toque y mantener) y no se puede "leer" el trazo. | 3 para quien usa lector | Es inherente a firmar dibujando. Una alternativa (firma tipeada o solo confirmación biométrica) cambiaría el modelo de evidencia y requiere decisión de la cátedra y cambios en la API. No se tocó. |
| L-02 | Las notificaciones push remotas no funcionan en Expo Go. | 2 | Usar build de desarrollo (`eas build --profile development`). Ver `docs/pruebas-moviles.md`. |
| L-03 | Un build *release* de Android bloquea `http://` (API local sin HTTPS). | 2 | Build de desarrollo o API con HTTPS. |
| L-04 | La firma exige conexión (la evidencia debe llegar al servidor). | 1 | Por diseño; la app lo avisa. |
| L-05 | El firmador no carga comentarios previos del servidor: la lista arranca vacía y solo muestra los publicados en la sesión. | 2 | Pendiente: depende de que la API exponga el listado. |
| L-06 | El modo oscuro arranca en "claro" por defecto; no sigue la preferencia del sistema hasta que la persona lo cambia. | 1 | Decisión de diseño; se puede cambiar en `use-sgc-theme.ts`. |
| L-07 | El mensaje de error de conexión del login dice "Verificá que Laravel esté levantado" y muestra la URL de la API: es texto técnico para desarrollo. | 1 | Dejarlo para la demo; reemplazarlo antes de una versión de producción. |
| L-08 | Pantallas sin revisión de etiquetas y hints en esta pasada: `convenios.tsx`, `movimientos.tsx`, `identidad.tsx`, `escanear-qr.tsx`, `proximamente.tsx`, `distribucion-cards.tsx`, `movimientos-card.tsx`, `ajuste-biometria.tsx`, `hint-row.tsx`. | 2 | Heredan `Txt` (tamaño, escala, encabezados). Revisarlas con TalkBack/VoiceOver; cambiar `Pressable` por `PressableA11y` y agregar `accessibilityLabel`/`accessibilityHint`. |
| L-09 | No hay tests de render ni de lector de pantalla: `jest` solo corre archivos `*.test.ts` de lógica. | 2 | La verificación de TalkBack/VoiceOver y teclado es manual (`revision-accesibilidad.md`). |
| L-10 | Las fechas se muestran con `toLocaleString()` del celular; el formato depende de su configuración regional. | 1 | Aceptable para la demo. |
| L-11 | Los resultados de la firma se informan con `Alert` nativo: es accesible pero desaparece al tocar "Aceptar". | 1 | La constancia queda disponible en "Compartir constancia". |
| L-12 | Con tamaño de fuente del sistema por encima de ×1,6 el texto se corta en ese tope (`maxFontSizeMultiplier`). | 1 | Se eligió para no romper la maquetación. |

## C. Cómo se registra uno nuevo

Agregar una fila con el siguiente ID (E-xx si se corrigió, L-xx si queda abierto), la gravedad, dónde ocurre y cómo reproducirlo. Los problemas de gravedad 2 y 3 surgidos de `pruebas-con-usuarios.md` entran acá antes de la presentación.
